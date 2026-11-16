import { TccEvaluator } from '../api/types';
import { Avatar } from './Avatar';
import { VerdictBadge } from './VerdictBadge';

export function EvaluatorList({ evaluators }: { evaluators: TccEvaluator[] }) {
  return (
    <ul className="people">
      {evaluators.map((e) => (
        <li key={e.id} className="person">
          <Avatar name={e.evaluator.name} />
          <div className="person-name">
            {e.evaluator.name}
            <VerdictBadge verdict={e.verdict} />
          </div>
          {e.comment && <p className="person-comment">{e.comment}</p>}
        </li>
      ))}
    </ul>
  );
}
