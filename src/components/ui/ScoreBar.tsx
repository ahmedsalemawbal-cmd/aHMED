import { cx } from './cx';
import { LEVEL_TEXT, scoreLevel } from './stages';

export interface ScoreBarProps {
  score: number;
  variant?: 'bar' | 'ring';
  /** 'opportunity' inside the app, 'presence' on the public client report */
  context?: 'opportunity' | 'presence';
  label?: string;
  /** ring diameter in px, default 140 */
  size?: number;
  className?: string;
}

export function ScoreBar({ score: raw, variant, context = 'opportunity', label, size: sizeProp, className }: ScoreBarProps) {
  const size = sizeProp || 140;
  const score = Math.max(0, Math.min(100, Math.round(raw || 0)));
  const lv = scoreLevel(score);
  const text = LEVEL_TEXT[context][lv];
  const cls = cx('md-score', `md-score-${context}-${lv}`, className);

  if (variant === 'ring') {
    const r = 52;
    const C = 2 * Math.PI * r;
    return (
      <div className={cx(cls, 'md-score-ring')} role="img" aria-label={`الدرجة ${score.toString()} من 100، ${text}`}>
        <svg viewBox="0 0 120 120" width={size} height={size}>
          <circle cx={60} cy={60} r={r} className="md-score-track" fill="none" strokeWidth={10} />
          <circle
            cx={60}
            cy={60}
            r={r}
            className="md-score-fill"
            fill="none"
            strokeWidth={10}
            strokeLinecap="round"
            strokeDasharray={C}
            strokeDashoffset={C * (1 - score / 100)}
            transform="rotate(-90 60 60)"
          />
        </svg>
        <div className="md-score-center" style={{ height: `${size.toString()}px` }}>
          <span className="md-score-num">{score}</span>
          <span className="md-score-of">من 100</span>
        </div>
        <div className="md-score-level">{text}</div>
      </div>
    );
  }

  return (
    <div className={cx(cls, 'md-score-bar')}>
      <div className="md-score-head">
        <span className="md-score-label">{label || 'الدرجة'}</span>
        <span className="md-score-value">
          <b>{score}</b>
          {' / 100 · '}
          <span className="md-score-level">{text}</span>
        </span>
      </div>
      <div
        className="md-score-track"
        role="meter"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={score}
        aria-valuetext={`${score.toString()} — ${text}`}
        aria-label={label || 'الدرجة'}
      >
        <div className="md-score-fill" style={{ width: `${score.toString()}%` }} />
        <span className="md-score-tick" style={{ insetInlineStart: '50%' }} />
        <span className="md-score-tick" style={{ insetInlineStart: '70%' }} />
      </div>
    </div>
  );
}
