import { describe, it, expect } from 'vitest';
import { pascalToCamel, transformKeys } from './keyTransform';

describe('pascalToCamel', () => {
  it('converts PascalCase to camelCase', () => {
    expect(pascalToCamel('PluginId')).toBe('pluginId');
    expect(pascalToCamel('IsSuccess')).toBe('isSuccess');
    expect(pascalToCamel('CreatedAt')).toBe('createdAt');
    expect(pascalToCamel('FilesUpdated')).toBe('filesUpdated');
  });

  it('handles all-uppercase short keys', () => {
    expect(pascalToCamel('ID')).toBe('id');
    expect(pascalToCamel('URL')).toBe('url');
  });

  it('handles multi-uppercase prefixes', () => {
    expect(pascalToCamel('WPVersion')).toBe('wpVersion');
    expect(pascalToCamel('HTTPStatus')).toBe('httpStatus');
  });

  it('leaves camelCase keys unchanged', () => {
    expect(pascalToCamel('pluginId')).toBe('pluginId');
    expect(pascalToCamel('isSuccess')).toBe('isSuccess');
    expect(pascalToCamel('name')).toBe('name');
  });

  it('handles empty and single-char keys', () => {
    expect(pascalToCamel('')).toBe('');
    expect(pascalToCamel('A')).toBe('a');
    expect(pascalToCamel('a')).toBe('a');
  });
});

describe('transformKeys', () => {
  it('transforms object keys recursively', () => {
    const input = {
      PluginId: 1,
      SiteName: 'Test',
      IsSuccess: true,
      Nested: {
        FilesUpdated: 5,
        ErrorMessage: 'none',
      },
    };
    expect(transformKeys(input)).toEqual({
      pluginId: 1,
      siteName: 'Test',
      isSuccess: true,
      nested: {
        filesUpdated: 5,
        errorMessage: 'none',
      },
    });
  });

  it('transforms arrays of objects', () => {
    const input = [
      { Id: 1, Name: 'A' },
      { Id: 2, Name: 'B' },
    ];
    expect(transformKeys(input)).toEqual([
      { id: 1, name: 'A' },
      { id: 2, name: 'B' },
    ]);
  });

  it('passes through primitives unchanged', () => {
    expect(transformKeys(42)).toBe(42);
    expect(transformKeys('hello')).toBe('hello');
    expect(transformKeys(null)).toBe(null);
    expect(transformKeys(undefined)).toBe(undefined);
    expect(transformKeys(true)).toBe(true);
  });

  it('handles mixed nested structures', () => {
    const input = {
      Results: [
        { PluginId: 1, Mappings: [{ SiteId: 10, RemoteSlug: 'test' }] },
      ],
    };
    expect(transformKeys(input)).toEqual({
      results: [
        { pluginId: 1, mappings: [{ siteId: 10, remoteSlug: 'test' }] },
      ],
    });
  });
});
