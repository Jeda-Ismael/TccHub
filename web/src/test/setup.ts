import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';
import { clearToasts } from '../components/Toaster';

afterEach(() => {
  cleanup();
  clearToasts();
  localStorage.clear();
});
