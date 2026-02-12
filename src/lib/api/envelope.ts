// Envelope detection and parsing utilities for the Universal Response Envelope.

import type {
  ApiResponse,
  ApiError,
  EnvelopeStatus,
  EnvelopeAttributes,
  EnvelopeNavigation,
  EnvelopeErrors,
  EnvelopeMethodsStack,
  EnvelopeMeta,
} from './types';

/** Raw envelope shape as received from the backend */
export interface RawEnvelope<T = unknown> {
  Status: EnvelopeStatus;
  Attributes: EnvelopeAttributes;
  Results: T[];
  Navigation?: EnvelopeNavigation;
  Errors?: EnvelopeErrors;
  MethodsStack?: EnvelopeMethodsStack;
}

/**
 * Detect whether a parsed JSON object is a PascalCase universal envelope.
 */
export function isEnvelope(obj: unknown): obj is RawEnvelope<unknown> {
  if (!obj || typeof obj !== 'object') return false;
  const o = obj as Record<string, unknown>;
  return (
    o.Status !== undefined &&
    typeof o.Status === 'object' &&
    o.Status !== null &&
    'IsSuccess' in (o.Status as Record<string, unknown>)
  );
}

/**
 * Convert a PascalCase envelope response into the legacy ApiResponse<T> shape.
 * For single items (IsSingle), data = Results[0].
 * For lists (IsMultiple), data = Results (as T).
 * Envelope metadata is preserved on the .envelope property.
 */
export function parseEnvelope<T>(env: RawEnvelope): ApiResponse<T> {
  // Auto-derive IsEmpty if not provided by backend
  if (env.Attributes.IsEmpty === undefined) {
    env.Attributes.IsEmpty = !Array.isArray(env.Results) || env.Results.length === 0;
  }
  if (env.Attributes.IsEmpty && env.Attributes.TotalRecords === undefined) {
    env.Attributes.TotalRecords = 0;
  }

  const meta: EnvelopeMeta = {
    attributes: env.Attributes,
    navigation: env.Navigation ?? undefined,
    errors: env.Errors ?? undefined,
    methodsStack: env.MethodsStack ?? undefined,
  };

  if (env.Status.IsFailed || env.Attributes.HasAnyErrors) {
    const errBlock = env.Errors;
    // Extract error code from BackendMessage if formatted as "[E1234] message"
    let code = 'E9999';
    let message = env.Status.Message || 'Unknown error';
    if (errBlock?.BackendMessage) {
      const match = errBlock.BackendMessage.match(/^\[([A-Z]\d+)\]\s*(.*)$/);
      if (match) {
        code = match[1];
        message = match[2];
      } else {
        message = errBlock.BackendMessage;
      }
    }

    return {
      success: false,
      error: {
        code,
        message,
        details: errBlock?.Backend?.length
          ? `Backend trace available (${errBlock.Backend.length} lines)`
          : undefined,
        context: {
          ...(env.Attributes.RequestedAt ? { requestedAt: env.Attributes.RequestedAt } : {}),
          ...(env.Attributes.RequestDelegatedAt ? { requestDelegatedAt: env.Attributes.RequestDelegatedAt } : {}),
          ...(env.Attributes.SessionId ? { sessionId: env.Attributes.SessionId } : {}),
          ...(errBlock?.Backend?.length ? { backendTrace: errBlock.Backend } : {}),
          ...(errBlock?.DelegatedServiceErrorStack?.length ? { delegatedServiceErrorStack: errBlock.DelegatedServiceErrorStack } : {}),
          ...(errBlock?.DelegatedRequestServer ? { delegatedRequestServer: errBlock.DelegatedRequestServer } : {}),
          ...(env.MethodsStack ? { methodsStack: env.MethodsStack } : {}),
        },
        timestamp: env.Status.Timestamp,
      },
      envelope: meta,
    };
  }

  // Success: extract data from Results
  let data: unknown;
  if (env.Attributes.IsSingle) {
    data = Array.isArray(env.Results) && env.Results.length > 0 ? env.Results[0] : env.Results;
  } else {
    data = env.Results;
  }

  return {
    success: true,
    data: data as T,
    envelope: meta,
  };
}

/** Quick check whether a text string looks like JSON */
export function looksLikeJson(text: string): boolean {
  const trimmed = text.trim();
  return trimmed.startsWith("{") || trimmed.startsWith("[");
}
