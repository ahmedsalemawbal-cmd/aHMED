import { useEffect, useMemo, useRef, useState } from 'react';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';

export const KEY_MAX = 90;
const MIC = 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3zM5 11a7 7 0 0 0 14 0M12 18v3';
const PLUS = 'M12 5v14M5 12h14';
const IMAGE = 'M4 5h16v14H4zM4 15l4-4 5 5 3-3 4 4';

function filesWord(n: number) {
  if (n === 1) return 'ملف واحد';
  if (n === 2) return 'ملفان';
  if (n <= 10) return `${n.toString()} ملفات`;
  return `${n.toString()} ملفاً`;
}

/** The two text fields of the notes step (the new-lead draft and the visit draft both have them). */
export interface NotesFields {
  keyObservation: string;
  notes: string;
}

export function Step3Notes({
  draft,
  update,
  media,
  setMedia,
  keyError,
  uploadNote = 'يُرفع الملف إلى مجلد خاص بك عند حفظ العميل، ولا يراه أحد غيرك.',
}: {
  draft: NotesFields;
  update: (patch: Partial<NotesFields>) => void;
  media: File[];
  setMedia: (files: File[]) => void;
  keyError?: string;
  /** body of the file sheet: when and where the file is uploaded */
  uploadNote?: string;
}) {
  const input = useRef<HTMLInputElement>(null);
  const [selected, setSelected] = useState<number | null>(null);
  const previews = useMemo(() => media.map((f) => (f.type.startsWith('image/') ? URL.createObjectURL(f) : null)), [media]);
  useEffect(
    () => () => {
      for (const p of previews) if (p) URL.revokeObjectURL(p);
    },
    [previews],
  );

  return (
    <div className="flex flex-col gap-[22px]">
      <div className="flex flex-col gap-2">
        <TextField
          label="الملاحظة الأبرز"
          required
          maxLength={KEY_MAX}
          value={draft.keyObservation}
          error={keyError}
          autoComplete="off"
          onChange={(e) => {
            update({ keyObservation: e.target.value.slice(0, KEY_MAX) });
          }}
        />
        <div className="flex justify-between text-label-sm font-normal text-ink-muted">
          <span>سطر واحد محدد يدخل في الرسالة، ليس مدحاً عاماً</span>
          <span dir="ltr" className="tabular-nums">{`${draft.keyObservation.length.toString()}/${KEY_MAX.toString()}`}</span>
        </div>
      </div>

      <div className="flex flex-col gap-[10px]">
        <span className="text-[15px] font-bold leading-[22px]">ملاحظة صوتية</span>
        <button type="button" className="md-btn md-btn-secondary md-btn-block" disabled aria-describedby="voice-soon">
          <svg className="md-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d={MIC} />
          </svg>
          <span>سجّل ملاحظة صوتية</span>
        </button>
        <span id="voice-soon" className="text-label-sm font-normal text-ink-muted">
          قريباً: الكلام يتحول إلى نص في الملاحظات.
        </span>
      </div>

      <TextField
        label="ملاحظات حرة"
        multiline
        rows={3}
        value={draft.notes}
        onChange={(e) => {
          update({ notes: e.target.value });
        }}
      />

      <div className="flex flex-col gap-[10px]">
        <div className="flex items-baseline justify-between">
          <span className="text-[15px] font-bold leading-[22px]">صور وفيديو</span>
          <span className="text-label-sm font-normal text-ink-muted">{media.length ? `${filesWord(media.length)} · تُرفع عند الحفظ` : 'اختياري'}</span>
        </div>
        <div className="grid grid-cols-4 gap-2">
          <button
            type="button"
            aria-label="إضافة صورة أو فيديو"
            onClick={() => input.current?.click()}
            className="flex aspect-square flex-col items-center justify-center gap-[2px] rounded-md border-[1.5px] border-dashed border-line-strong bg-surface-raised text-caption text-ink"
          >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              <path d={PLUS} />
            </svg>
            إضافة
          </button>
          {media.map((f, i) => {
            const video = f.type.startsWith('video/');
            const src = previews[i];
            return (
              <button
                key={`${f.name}-${i.toString()}`}
                type="button"
                aria-label={`${video ? 'فيديو' : 'صورة'} ${(i + 1).toString()}: خيارات`}
                onClick={() => {
                  setSelected(i);
                }}
                className={`flex aspect-square flex-col items-center justify-center gap-[2px] overflow-hidden rounded-md border-0 p-0 text-caption ${video ? 'bg-ink text-surface-raised' : 'bg-surface-sunken text-ink-muted'}`}
              >
                {src ? (
                  <img src={src} alt="" className="size-full object-cover" />
                ) : (
                  <>
                    <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                      {video ? <path d="M8 5v14l11-7z" fill="currentColor" /> : <path d={IMAGE} fill="none" stroke="currentColor" strokeWidth="2" />}
                    </svg>
                    {video ? 'فيديو' : 'صورة'}
                  </>
                )}
              </button>
            );
          })}
        </div>
        <input
          ref={input}
          type="file"
          accept="image/*,video/*"
          multiple
          className="sr-only"
          tabIndex={-1}
          aria-hidden="true"
          onChange={(e) => {
            const files = Array.from(e.target.files ?? []);
            if (files.length) setMedia([...media, ...files]);
            e.target.value = '';
          }}
        />
      </div>

      <BottomSheet
        open={selected !== null}
        onClose={() => {
          setSelected(null);
        }}
        title="الملف"
        actions={
          <>
            <Button
              variant="danger"
              block
              onClick={() => {
                if (selected !== null) setMedia(media.filter((_, j) => j !== selected));
                setSelected(null);
              }}
            >
              احذف الملف
            </Button>
            <Button
              variant="ghost"
              block
              onClick={() => {
                setSelected(null);
              }}
            >
              إلغاء
            </Button>
          </>
        }
      >
        {uploadNote}
      </BottomSheet>
    </div>
  );
}
