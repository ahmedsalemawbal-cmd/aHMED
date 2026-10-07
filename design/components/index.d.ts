import type * as React from 'react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** default 'secondary' */
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'whatsapp';
  /** 'md' (52px, mobile) | 'sm' (40px, desktop only) */
  size?: 'md' | 'sm';
  block?: boolean;
  /** internal glyph: plus, chat, phone, check, x, clock, pin, store */
  icon?: string;
  loading?: boolean;
  children?: React.ReactNode;
}
export declare function Button(props: ButtonProps): React.ReactElement;

export interface TextFieldProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label: React.ReactNode;
  hint?: React.ReactNode;
  /** replaces the hint; says what happened and how to fix it */
  error?: React.ReactNode;
  multiline?: boolean;
  rows?: number;
  /** unit shown at the end, e.g. 'ر.س' */
  suffix?: React.ReactNode;
  /** left-to-right input for phone numbers and URLs */
  ltr?: boolean;
}
export declare function TextField(props: TextFieldProps): React.ReactElement;

export type Stage = 'not_visited' | 'visited' | 'contacted' | 'replied' | 'meeting' | 'proposal' | 'won' | 'lost';
export interface StageBadgeProps { stage: Stage; size?: 'md' | 'sm'; children?: React.ReactNode; }
export declare function StageBadge(props: StageBadgeProps): React.ReactElement;
export declare const STAGES: { id: Stage; label: string }[];

export interface PriorityBadgeProps { priority: 'hot' | 'warm' | 'cold'; size?: 'md' | 'sm'; }
export declare function PriorityBadge(props: PriorityBadgeProps): React.ReactElement;

export interface LeadCardProps {
  variant?: 'full' | 'compact';
  businessName: string;
  activity: string;
  contactName?: string;
  stage?: Stage;
  score?: number;
  priority?: 'hot' | 'warm' | 'cold';
  lastContact?: string;
  nextAction?: string;
  nextActionAt?: string;
  overdue?: boolean;
  /** compact only */
  value?: string;
  daysInStage?: number;
  /** compact: more than 7 days without movement */
  stale?: boolean;
  onClick?: () => void;
  onWhatsApp?: () => void;
}
export declare function LeadCard(props: LeadCardProps): React.ReactElement;

export interface ScoreBarProps {
  score: number;
  variant?: 'bar' | 'ring';
  /** 'opportunity' inside the app, 'presence' on the public client report */
  context?: 'opportunity' | 'presence';
  label?: string;
  /** ring diameter in px, default 140 */
  size?: number;
}
export declare function ScoreBar(props: ScoreBarProps): React.ReactElement;

export interface TriSelectProps {
  label: React.ReactNode;
  value: 'yes' | 'partial' | 'no' | null;
  onChange?: (value: 'yes' | 'partial' | 'no') => void;
  weight?: number;
  /** yes / no only (activity-specific items) */
  binary?: boolean;
  name?: string;
}
export declare function TriSelect(props: TriSelectProps): React.ReactElement;

export interface BottomSheetProps {
  open: boolean;
  onClose?: () => void;
  title?: string;
  children?: React.ReactNode;
  actions?: React.ReactNode;
}
export declare function BottomSheet(props: BottomSheetProps): React.ReactElement | null;

export interface ToastProps {
  tone?: 'success' | 'error' | 'info';
  title: React.ReactNode;
  message?: React.ReactNode;
  action?: string;
  onAction?: () => void;
}
export declare function Toast(props: ToastProps): React.ReactElement;

export interface EmptyStateProps {
  title: React.ReactNode;
  message?: React.ReactNode;
  action?: React.ReactNode;
  icon?: string;
}
export declare function EmptyState(props: EmptyStateProps): React.ReactElement;

declare global { interface Window { Maidani: { Button: typeof Button; TextField: typeof TextField; StageBadge: typeof StageBadge; PriorityBadge: typeof PriorityBadge; LeadCard: typeof LeadCard; ScoreBar: typeof ScoreBar; TriSelect: typeof TriSelect; BottomSheet: typeof BottomSheet; Toast: typeof Toast; EmptyState: typeof EmptyState; STAGES: typeof STAGES } } }
