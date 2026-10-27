// jest-dom adds custom jest matchers for asserting on DOM nodes.
// allows you to do things like:
// expect(element).toHaveTextContent(/react/i)
// learn more: https://github.com/testing-library/jest-dom
import '@testing-library/jest-dom';
import { vi } from 'vitest';

// Make jest available globally for compatibility with old tests
const globalWithJest = globalThis as typeof globalThis & { jest: typeof vi };
globalWithJest.jest = vi;
