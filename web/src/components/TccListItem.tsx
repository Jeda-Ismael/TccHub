import { Link } from 'react-router-dom';
import { Tcc } from '../api/types';
import { StatusBadge } from './StatusBadge';
import { ChevronRightIcon } from './icons';

export function TccListItem({ tcc, to, subtitle }: { tcc: Tcc; to: string; subtitle: string }) {
  return (
    <Link to={to} className="tcc-list-item">
      <div className="tcc-list-item-main">
        <h3>{tcc.title}</h3>
        <p>{subtitle}</p>
      </div>
      <div className="tcc-list-item-side">
        <StatusBadge status={tcc.status} />
        <span className="chevron">
          <ChevronRightIcon />
        </span>
      </div>
    </Link>
  );
}
