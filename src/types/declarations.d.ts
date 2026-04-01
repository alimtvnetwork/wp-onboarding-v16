// Module declarations for packages without bundled types
declare module 'highlight.js/lib/core' {
  import hljs from 'highlight.js';
  export default hljs;
}

declare module 'highlight.js/lib/languages/*' {
  import { LanguageFn } from 'highlight.js';
  const lang: LanguageFn;
  export default lang;
}

declare module 'swagger-ui-react' {
  import { ComponentType } from 'react';
  interface SwaggerUIProps {
    url?: string;
    spec?: object;
    [key: string]: any;
  }
  const SwaggerUI: ComponentType<SwaggerUIProps>;
  export default SwaggerUI;
}
