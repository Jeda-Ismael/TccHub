import { FileDownIcon } from './icons';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:3000';

export function DocumentLink({ url, children }: { url: string; children: React.ReactNode }) {
  return (
    <a className="doc-link" href={`${API_URL}${url}`} target="_blank" rel="noreferrer">
      <FileDownIcon />
      {children}
    </a>
  );
}
