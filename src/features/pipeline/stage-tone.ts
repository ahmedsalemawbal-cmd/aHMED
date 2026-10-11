import type { Stage } from '@/components/ui/stages';

export interface StageTone {
  /** stage colour as text (label and dot through currentColor) */
  text: string;
  /** the stage's soft background */
  soft: string;
  /** a border in the stage colour (selected tab, column top) */
  border: string;
  /** drop-target outline */
  outline: string;
}

/** Literal class names per stage, so Tailwind sees every one (colours come from tokens only). */
export const STAGE_TONE: Record<Stage, StageTone> = {
  not_visited: { text: 'text-stage-not-visited', soft: 'bg-stage-not-visited-soft', border: 'border-stage-not-visited', outline: 'outline-stage-not-visited' },
  visited: { text: 'text-stage-visited', soft: 'bg-stage-visited-soft', border: 'border-stage-visited', outline: 'outline-stage-visited' },
  contacted: { text: 'text-stage-contacted', soft: 'bg-stage-contacted-soft', border: 'border-stage-contacted', outline: 'outline-stage-contacted' },
  replied: { text: 'text-stage-replied', soft: 'bg-stage-replied-soft', border: 'border-stage-replied', outline: 'outline-stage-replied' },
  meeting: { text: 'text-stage-meeting', soft: 'bg-stage-meeting-soft', border: 'border-stage-meeting', outline: 'outline-stage-meeting' },
  proposal: { text: 'text-stage-proposal', soft: 'bg-stage-proposal-soft', border: 'border-stage-proposal', outline: 'outline-stage-proposal' },
  won: { text: 'text-stage-won', soft: 'bg-stage-won-soft', border: 'border-stage-won', outline: 'outline-stage-won' },
  lost: { text: 'text-stage-lost', soft: 'bg-stage-lost-soft', border: 'border-stage-lost', outline: 'outline-stage-lost' },
};
