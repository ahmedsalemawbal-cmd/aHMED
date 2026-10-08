import type { Priority } from '@/components/ui/stages';

/**
 * brief.md «أولوية العميل»:
 *   hot  — score < 50 and the contact decides
 *   warm — score 50–69, or < 50 and the contact does not decide
 *   cold — score ≥ 70
 * A lead that replied is hot regardless (opening a report no longer counts — decisions §1).
 */
export function computePriority(score: number, isDecisionMaker: boolean, replied = false): Priority {
  if (replied) return 'hot';
  if (score < 50) return isDecisionMaker ? 'hot' : 'warm';
  if (score < 70) return 'warm';
  return 'cold';
}

/** Owner decides (QUESTIONS.md Q17, accepted proposal); editable later on the lead page. */
export function defaultDecisionMaker(role: 'owner' | 'manager' | 'employee' | null): boolean {
  return role === 'owner';
}
