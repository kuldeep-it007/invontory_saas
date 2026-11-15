/**
 * React 19's types no longer declare a global `JSX` namespace; this codebase
 * annotates components as `JSX.Element`. Re-expose React's JSX types globally
 * so those annotations keep working without touching every component.
 */
import type { JSX as ReactJSX } from "react";

declare global {
  namespace JSX {
    type Element = ReactJSX.Element;
    type ElementClass = ReactJSX.ElementClass;
    type IntrinsicElements = ReactJSX.IntrinsicElements;
    type IntrinsicAttributes = ReactJSX.IntrinsicAttributes;
  }
}

export {};