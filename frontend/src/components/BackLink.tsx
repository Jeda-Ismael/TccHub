import { Link } from 'react-router-dom';
import { ChevronLeftIcon } from './icons';

export function BackLink({ to, children }: { to: string; children: React.ReactNode }) {
  return (
    <Link to={to} className="back-link">
      <ChevronLeftIcon size={16} />
      {children}
    </Link>
  );
}
