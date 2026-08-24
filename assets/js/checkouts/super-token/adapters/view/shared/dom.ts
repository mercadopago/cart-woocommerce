/**
 * Tiny declarative DOM builder shared by the variant views. Keeps rendering XSS-safe by
 * construction: text is set via `textContent` and attributes via `setAttribute`, never
 * `innerHTML`. Lets the views describe the element tree instead of imperatively creating,
 * configuring and appending each node.
 */
export interface ElOptions {
  classes?: string[];
  /** Text content — always assigned via textContent, so it can never become markup. */
  text?: string;
  attrs?: Record<string, string>;
  dataset?: Record<string, string | undefined>;
  children?: (Node | null)[];
}

export function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  options: ElOptions = {},
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  if (options.classes?.length) {
    node.classList.add(...options.classes);
  }
  if (options.text !== undefined) {
    node.textContent = options.text;
  }
  if (options.attrs) {
    Object.entries(options.attrs).forEach(([name, value]) => node.setAttribute(name, value));
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([key, value]) => {
      if (value !== undefined) {
        node.dataset[key] = value;
      }
    });
  }
  options.children?.forEach((child) => {
    if (child) {
      node.appendChild(child);
    }
  });
  return node;
}
