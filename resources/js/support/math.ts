import renderMathInElement from 'katex/dist/contrib/auto-render';

export type MathDelimiter = { left: string; right: string; display: boolean };

export const MATH_DELIMITERS: MathDelimiter[] = [
    { left: '\\(', right: '\\)', display: false },
    { left: '\\[', right: '\\]', display: true },
    { left: '$$', right: '$$', display: true },
    { left: '$', right: '$', display: false },
];

export function renderMath(element: HTMLElement): void {
    renderMathInElement(element, {
        delimiters: MATH_DELIMITERS,
        throwOnError: false,
        strict: false,
    });
}

export type MathSnippet = { label: string; insert: string; hint: string };

export const MATH_SNIPPETS: MathSnippet[] = [
    { label: '½', insert: '\\(\\frac{a}{b}\\)', hint: 'Fraction' },
    { label: '√', insert: '\\(\\sqrt{x}\\)', hint: 'Square root' },
    { label: 'xⁿ', insert: '\\(x^{n}\\)', hint: 'Superscript' },
    { label: 'xₙ', insert: '\\(x_{n}\\)', hint: 'Subscript' },
    { label: '∫', insert: '\\(\\int_{a}^{b} f(x)\\,dx\\)', hint: 'Integral' },
    { label: '∑', insert: '\\(\\sum_{i=1}^{n} x_i\\)', hint: 'Summation' },
    { label: 'π', insert: '\\(\\pi\\)', hint: 'Pi' },
    { label: 'θ', insert: '\\(\\theta\\)', hint: 'Theta' },
    { label: '∞', insert: '\\(\\infty\\)', hint: 'Infinity' },
    { label: '±', insert: '\\(\\pm\\)', hint: 'Plus-minus' },
    { label: '×', insert: '\\(\\times\\)', hint: 'Times' },
    { label: '÷', insert: '\\(\\div\\)', hint: 'Divide' },
    { label: '≈', insert: '\\(\\approx\\)', hint: 'Approximately' },
    { label: '≠', insert: '\\(\\neq\\)', hint: 'Not equal' },
    { label: '≤', insert: '\\(\\leq\\)', hint: 'Less or equal' },
    { label: '≥', insert: '\\(\\geq\\)', hint: 'Greater or equal' },
    { label: '[]', insert: '\\[\n\\]', hint: 'Display block' },
];

export function insertAtCursor(field: HTMLTextAreaElement | HTMLInputElement, snippet: string): void {
    const start = field.selectionStart ?? field.value.length;
    const end = field.selectionEnd ?? field.value.length;
    field.value = `${field.value.slice(0, start)}${snippet}${field.value.slice(end)}`;
    const cursor = start + snippet.length;
    field.focus();
    field.setSelectionRange(cursor, cursor);
    field.dispatchEvent(new Event('input', { bubbles: true }));
}
