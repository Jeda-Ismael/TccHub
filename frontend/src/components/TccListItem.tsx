import { Link } from 'react-router-dom';
import { Tcc } from '../api/types';
import { StatusBadge } from './StatusBadge';

export function TccListItem({ tcc, to, subtitle }: { tcc: Tcc; to: string; subtitle: string }) {
  return (
    <Link to={to} className="tcc-list-item">
      <div>
        <h3>{tcc.title}</h3>
        <p>{subtitle}</p>
      </div>
      <StatusBadge status={tcc.status} />
    </Link>
  );
}
