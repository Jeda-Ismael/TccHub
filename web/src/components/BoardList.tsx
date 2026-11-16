import { BoardMember } from '../api/types';
import { VerdictBadge } from './VerdictBadge';
import { Avatar } from './Avatar';

export function BoardList({ board, title = 'Banca' }: { board: BoardMember[]; title?: string }) {
  return (
    <div className="card">
      <h3>{title}</h3>
      {board.length === 0 ? (
        <p className="muted">A banca ainda não foi montada pela coordenação.</p>
      ) : (
        <ul className="board-list">
          {board.map((member) => (
            <li key={member.id}>
              <div className="board-member">
                <Avatar name={member.evaluator.name} />
                <div>
                  <strong>{member.evaluator.name}</strong>
                  {member.isMe && <span className="muted"> (você)</span>}
                  {member.comment && <p className="muted">"{member.comment}"</p>}
                </div>
              </div>
              <VerdictBadge verdict={member.verdict} />
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
