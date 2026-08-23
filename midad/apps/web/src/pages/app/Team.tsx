import React, { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useApp } from '../../lib/store'
import { useAsync } from '../../lib/hooks'
import { callFunction, supabase } from '../../lib/supabase'
import { fetchDocuments, fetchTeam } from '../../lib/data'
import { isValidPhone, normalizePhone } from '../../lib/config'
import type { Profile } from '../../lib/types'
import { fmtNum, fmtRelative } from '../../lib/format'
import {
  Alert, Avatar, Badge, Button, Card, ConfirmModal, ErrorState, Field, IconButton,
  Input, Modal, PageHead, Progress, Select, SkeletonRows,
} from '../../ui/kit'
import { IcCheck, IcKey, IcLock, IcPlus, IcTeam } from '../../ui/icons'

const ROLE_TONES: ('accent' | 'info' | 'success' | 'warn')[] = ['accent', 'info', 'success', 'warn']

function generatePassword(): string {
  const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  const bytes = new Uint32Array(10)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, (b) => chars[b % chars.length]).join('')
}

export default function Team() {
  const { subscriber, profile, plan, roles, toast } = useApp()
  const nav = useNavigate()
  const sid = subscriber?.id
  const isSolo = subscriber?.account_type === 'teacher'

  const [adding, setAdding] = useState(false)
  const [form, setForm] = useState({ full_name: '', phone: '', email: '', role_key: '', password: '' })
  const [formErr, setFormErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [fresh, setFresh] = useState<string | null>(null)
  const [suspending, setSuspending] = useState<Profile | null>(null)
  const [resetting, setResetting] = useState<Profile | null>(null)
  const [newPass, setNewPass] = useState('')

  const { data, loading, error, reload } = useAsync(async () => {
    if (!sid || isSolo) return null
    const [team, docs] = await Promise.all([fetchTeam(sid), fetchDocuments(sid)])
    const counts = new Map<string, number>()
    for (const d of docs) counts.set(d.owner_id, (counts.get(d.owner_id) || 0) + 1)
    return { team, counts }
  }, [sid, isSolo])

  const team: Profile[] = data?.team || []
  const counts = data?.counts || new Map<string, number>()

  const seats = plan?.seats ?? 1
  const used = team.filter((m) => m.status === 'active').length
  const full = used >= seats

  useEffect(() => {
    if (!fresh) return
    const t = setTimeout(() => setFresh(null), 2000)
    return () => clearTimeout(t)
  }, [fresh])

  const roleTone = useMemo(() => {
    const m = new Map<string, 'accent' | 'info' | 'success' | 'warn'>()
    roles.forEach((r, i) => m.set(r.key, ROLE_TONES[i % ROLE_TONES.length]))
    return m
  }, [roles])

  const roleName = (k: string) => roles.find((r) => r.key === k)?.name_ar || k
  const roleBlurb = (k: string) => roles.find((r) => r.key === k)?.blurb_ar || ''

  function openAdd() {
    setForm({ full_name: '', phone: '', email: '', role_key: roles[0]?.key || 'teacher', password: generatePassword() })
    setFormErr(null); setAdding(true)
  }

  async function addMember() {
    setFormErr(null)
    if (form.full_name.trim().length < 3) { setFormErr('اكتب اسم العضو كاملًا'); return }
    if (!isValidPhone(form.phone)) { setFormErr('أدخل رقم جوّال صحيحًا من 10 أرقام يبدأ بـ 05'); return }
    if (form.password.length < 8) { setFormErr('كلمة المرور 8 أحرف على الأقلّ'); return }
    setBusy(true)
    try {
      const res = await callFunction<{ member: Profile }>('team', {
        action: 'add_member',
        full_name: form.full_name.trim(),
        phone: normalizePhone(form.phone),
        email: form.email.trim() || null,
        role_key: form.role_key,
        password: form.password,
      })
      setAdding(false)
      setFresh(res.member?.id || null)
      toast(`أُضيف ${form.full_name.trim()} إلى الفريق`)
      reload()
    } catch (e: any) {
      setFormErr(e?.message || 'تعذّر إضافة العضو')
    } finally { setBusy(false) }
  }

  async function changeRole(m: Profile, role_key: string) {
    const { error: e } = await supabase.from('profiles').update({ role_key }).eq('id', m.id)
    if (e) { toast('تعذّر تغيير الدور', 'danger'); return }
    toast(`صار دور ${m.full_name}: ${roleName(role_key)}`)
    reload()
  }

  async function setStatus(m: Profile, status: 'active' | 'suspended') {
    setBusy(true)
    try {
      await callFunction('team', { action: 'set_member_status', user_id: m.id, status })
      toast(status === 'suspended' ? `أُوقف ${m.full_name}` : `عاد ${m.full_name} إلى الفريق`)
      setSuspending(null)
      reload()
    } catch (e: any) {
      toast(e?.message || 'تعذّر تغيير الحالة', 'danger')
    } finally { setBusy(false) }
  }

  async function resetPassword() {
    if (!resetting) return
    if (newPass.length < 8) { toast('كلمة المرور 8 أحرف على الأقلّ', 'danger'); return }
    setBusy(true)
    try {
      await callFunction('team', { action: 'reset_password', user_id: resetting.id, password: newPass })
      toast(`غُيّرت كلمة مرور ${resetting.full_name}`)
      setResetting(null)
    } catch (e: any) {
      toast(e?.message || 'تعذّر تغيير كلمة المرور', 'danger')
    } finally { setBusy(false) }
  }

  /* ---------- اشتراك المعلّم: لا فريق ---------- */
  if (isSolo) return <SoloExplainer onPlans={() => nav('/app/plans')} />

  if (error) return <ErrorState onRetry={reload} message={error} />

  return (
    <>
      <PageHead
        title="الفريق"
        sub={subscriber?.name || ''}
        actions={
          <Button auto variant="primary" icon={<IcPlus size={16} />} disabled={full || !profile?.is_owner} onClick={openAdd}>
            إضافة عضو
          </Button>
        }
      />

      <Card className="mdd-col" style={{ gap: 12, marginBlockEnd: 'var(--mdd-s-5)' }}>
        <div className="mdd-row mdd-row--between mdd-row--wrap" style={{ gap: 12 }}>
          <div>
            <div style={{ fontSize: 20, fontWeight: 700 }}>
              <span className="mdd-num">{used}</span> من <span className="mdd-num">{seats}</span> مقاعد مستعملة — بينها حسابك
            </div>
            <span style={{ fontSize: 12.5, color: 'var(--mdd-text-3)' }}>
              {plan?.name_ar || 'باقتك الحالية'} — العضو الموقوف لا يشغل مقعدًا.
            </span>
          </div>
          {full && (
            <Link to="/app/plans"><Button auto variant="soft" icon={<IcLock size={15} />}>ارفع باقتك</Button></Link>
          )}
        </div>
        <Progress value={used} max={seats} tone={full ? 'warn' : undefined} />
      </Card>

      {full && (
        <div style={{ marginBlockEnd: 'var(--mdd-s-5)' }}>
          <Alert tone="warn">
            اكتملت مقاعد باقتك — لإضافة عضوٍ جديد ارفع باقتك، أو أوقف عضوًا لم يعد يعمل معك فيتحرّر مقعده.
            <Link to="/app/plans" style={{ color: 'var(--mdd-accent)', fontWeight: 700 }}> شاهد الباقات</Link>
          </Alert>
        </div>
      )}

      {loading ? (
        <SkeletonRows n={4} />
      ) : (
        <>
          <div className="mdd-table-wrap mdd-table-wrap--cards">
            <table className="mdd-table">
              <thead>
                <tr>
                  <th>العضو</th>
                  <th>الجوّال</th>
                  <th>الدور</th>
                  <th>الحالة</th>
                  <th>آخر دخول</th>
                  <th>ملفّاته</th>
                  <th style={{ width: 190 }}>أفعال</th>
                </tr>
              </thead>
              <tbody>
                {team.map((m) => {
                  const suspended = m.status === 'suspended'
                  return (
                    <tr key={m.id} style={{
                      background: fresh === m.id ? 'var(--mdd-accent-soft)' : undefined,
                      transition: 'background var(--mdd-dur) var(--mdd-ease)',
                      opacity: suspended ? 0.6 : 1,
                    }}>
                      <td data-label="العضو">
                        <div className="mdd-row" style={{ gap: 10 }}>
                          <Avatar name={m.full_name} size="sm" />
                          <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600 }}>{m.full_name}</div>
                            {m.email && <div style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>{m.email}</div>}
                          </div>
                          {m.is_owner && <Badge tone="accent">المالك</Badge>}
                        </div>
                      </td>
                      <td data-label="الجوّال"><span className="mdd-num mdd-mono">{m.phone}</span></td>
                      <td data-label="الدور">
                        {m.is_owner || !profile?.is_owner ? (
                          <Badge tone={roleTone.get(m.role_key) || 'neutral'}>{roleName(m.role_key)}</Badge>
                        ) : (
                          <Select value={m.role_key} onChange={(e) => changeRole(m, e.target.value)} aria-label={`دور ${m.full_name}`}>
                            {roles.map((r) => <option key={r.key} value={r.key}>{r.name_ar}</option>)}
                          </Select>
                        )}
                      </td>
                      <td data-label="الحالة">
                        <Badge tone={suspended ? 'danger' : 'success'} dot>{suspended ? 'موقوف' : 'نشِط'}</Badge>
                      </td>
                      <td data-label="آخر دخول">
                        <span className="mdd-muted">{m.last_login_at ? fmtRelative(m.last_login_at) : 'لم يدخل بعد'}</span>
                      </td>
                      <td data-label="ملفّاته"><span className="mdd-num">{fmtNum(counts.get(m.id) || 0)}</span></td>
                      <td data-label="أفعال">
                        {profile?.is_owner ? (
                          <div className="mdd-row" style={{ gap: 6 }}>
                            <IconButton label="إعادة تعيين كلمة المرور"
                              onClick={() => { setResetting(m); setNewPass(generatePassword()) }}>
                              <IcKey size={14} />
                            </IconButton>
                            {m.is_owner ? (
                              <span style={{ fontSize: 11.5, color: 'var(--mdd-text-3)' }}>لا يُوقَف</span>
                            ) : suspended ? (
                              <Button auto size="sm" variant="secondary" icon={<IcCheck size={14} />} onClick={() => setStatus(m, 'active')}>
                                أعد التفعيل
                              </Button>
                            ) : (
                              <Button auto size="sm" variant="danger" onClick={() => setSuspending(m)}>أوقف</Button>
                            )}
                          </div>
                        ) : <span className="mdd-muted">—</span>}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {team.length <= 1 && (
            <Card className="mdd-col" style={{ gap: 12, alignItems: 'center', textAlign: 'center', marginBlockStart: 'var(--mdd-s-5)' }}>
              <span style={{ color: 'var(--mdd-accent-soft-fg)' }}><IcTeam size={44} /></span>
              <h3 style={{ fontSize: 17 }}>أنت وحدك في الحساب حتّى الآن</h3>
              <p className="mdd-prose" style={{ fontSize: 13, maxWidth: 480 }}>
                باقتك تفتح <span className="mdd-num">{seats}</span> مقاعد — بينها حسابك أنت. أضف وكيلًا أو مرشدًا أو معلّمًا،
                فيدخل بجوّاله وكلمة المرور التي تعطيه إيّاها، ويرى قوالب دوره.
              </p>
              <Button auto variant="primary" disabled={full || !profile?.is_owner} onClick={openAdd}>أضف أوّل عضو</Button>
            </Card>
          )}
        </>
      )}

      {/* إضافة عضو */}
      <Modal open={adding} onClose={() => setAdding(false)} title="إضافة عضو إلى الفريق"
        footer={
          <>
            <Button variant="secondary" onClick={() => setAdding(false)} block>إلغاء</Button>
            <Button variant="primary" onClick={addMember} loading={busy} block>أضف العضو</Button>
          </>
        }>
        {formErr && <Alert tone="danger">{formErr}</Alert>}
        <Field label="الاسم الكامل">
          <Input value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })}
            placeholder="نورة عبدالله القحطاني" autoFocus />
        </Field>
        <Field label="الجوّال" help="هو اسم الدخول — عشرة أرقام تبدأ بـ 05.">
          <Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })}
            placeholder="0512345678" ltr inputMode="numeric" />
        </Field>
        <Field label="البريد (اختياريّ)">
          <Input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} ltr type="email" />
        </Field>
        <Field label="الدور" help={roleBlurb(form.role_key) || 'الدور يحدّد القوالب التي يراها العضو في المكتبة.'}>
          <Select value={form.role_key} onChange={(e) => setForm({ ...form, role_key: e.target.value })}>
            {roles.map((r) => <option key={r.key} value={r.key}>{r.name_ar}</option>)}
          </Select>
        </Field>
        <Field label="كلمة المرور المبدئية">
          <div className="mdd-row" style={{ gap: 8 }}>
            <Input value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} ltr style={{ flex: 1 }} />
            <Button auto variant="secondary" onClick={() => setForm({ ...form, password: generatePassword() })}>ولّد كلمة</Button>
          </div>
        </Field>
        <Alert tone="info">سيدخل بجوّاله وكلمة المرور هذه — أرسلها إليه، وليغيّرها من شاشة «حسابي» بعد أوّل دخول.</Alert>
      </Modal>

      {/* إعادة تعيين كلمة المرور */}
      <Modal open={!!resetting} onClose={() => setResetting(null)} title={`كلمة مرور ${resetting?.full_name || ''}`}
        footer={
          <>
            <Button variant="secondary" onClick={() => setResetting(null)} block>إلغاء</Button>
            <Button variant="primary" onClick={resetPassword} loading={busy} block>احفظ الكلمة</Button>
          </>
        }>
        <Field label="كلمة المرور الجديدة" help="ثمانية أحرف على الأقلّ.">
          <div className="mdd-row" style={{ gap: 8 }}>
            <Input value={newPass} onChange={(e) => setNewPass(e.target.value)} ltr style={{ flex: 1 }} />
            <Button auto variant="secondary" onClick={() => setNewPass(generatePassword())}>ولّد كلمة</Button>
          </div>
        </Field>
        <Alert tone="warn">تتوقّف كلمته القديمة فورًا — أبلغه بالجديدة.</Alert>
      </Modal>

      <ConfirmModal
        open={!!suspending} onClose={() => setSuspending(null)}
        onConfirm={() => suspending && setStatus(suspending, 'suspended')}
        loading={busy} danger title={`إيقاف ${suspending?.full_name || ''}`} confirmLabel="أوقف العضو"
        body="يبقى ما أنشأه، ولا يستطيع الدخول. يتحرّر مقعده لعضوٍ آخر، وتستطيع إعادة تفعيله في أيّ وقت."
      />
    </>
  )
}

/* ============ اشتراك المعلّم ============ */
function SoloExplainer({ onPlans }: { onPlans: () => void }) {
  return (
    <>
      <PageHead title="الفريق" sub="اشتراكك الحاليّ لشخصٍ واحد" />
      <Card className="mdd-col" style={{ gap: 18, alignItems: 'center', textAlign: 'center', padding: 'var(--mdd-s-8)' }}>
        <span style={{ color: 'var(--mdd-accent-soft-fg)' }}><ArtSolo /></span>
        <div>
          <h2 style={{ fontSize: 22 }}>اشتراك المعلّم لشخص واحد</h2>
          <p className="mdd-prose" style={{ fontSize: 14, marginBlockStart: 8, maxWidth: 520, marginInline: 'auto' }}>
            إن كنت تريد أن يدخل زملاؤك، فباقة المدرسة تفتح مقاعد بأدوارٍ مختلفة —
            كلّ عضوٍ يدخل بجوّاله ويرى قوالب دوره، والملفّات تبقى في حساب المدرسة.
          </p>
        </div>

        <div className="mdd-grid mdd-grid--2" style={{ width: '100%', maxWidth: 620, textAlign: 'start' }}>
          <Card className="mdd-col" style={{ gap: 8 }}>
            <h3 style={{ fontSize: 15 }}>اشتراك المعلّم</h3>
            <CompareLine ok>مقعدٌ واحد لك</CompareLine>
            <CompareLine ok>قوالب دورك وملفّاتك</CompareLine>
            <CompareLine>لا أعضاء ولا أدوار</CompareLine>
            <CompareLine>لا ملفّات مشتركة بين الزملاء</CompareLine>
          </Card>
          <Card className="mdd-col" style={{ gap: 8, borderColor: 'var(--mdd-accent)' }}>
            <h3 style={{ fontSize: 15 }}>باقة المدرسة</h3>
            <CompareLine ok>مقاعد متعدّدة بأدوار</CompareLine>
            <CompareLine ok>وكيل · مرشد · معلّم</CompareLine>
            <CompareLine ok>ملفّات المدرسة في مكانٍ واحد</CompareLine>
            <CompareLine ok>بيانات ترويسة موحّدة للجميع</CompareLine>
          </Card>
        </div>

        <Button auto variant="primary" size="lg" onClick={onPlans}>شاهد باقة المدرسة</Button>
      </Card>
    </>
  )
}

function CompareLine({ ok, children }: { ok?: boolean; children: React.ReactNode }) {
  return (
    <div className="mdd-row" style={{ gap: 9, fontSize: 13, color: ok ? 'var(--mdd-text)' : 'var(--mdd-text-3)' }}>
      <span style={{ color: ok ? 'var(--mdd-accent)' : 'var(--mdd-text-3)', flex: 'none', display: 'inline-flex' }}>
        {ok ? <IcCheck size={14} /> : <IcLock size={14} />}
      </span>
      <span style={{ minWidth: 0 }}>{children}</span>
    </div>
  )
}

function ArtSolo() {
  return (
    <svg width="120" height="86" viewBox="0 0 120 86" fill="none" aria-hidden="true">
      <circle cx="34" cy="26" r="11" stroke="currentColor" strokeWidth="2.6" />
      <path d="M16 66c0-10 8-16 18-16s18 6 18 16" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" />
      <circle cx="80" cy="26" r="11" stroke="currentColor" strokeWidth="2.4" strokeDasharray="4 5" />
      <path d="M62 66c0-10 8-16 18-16s18 6 18 16" stroke="currentColor" strokeWidth="2.4" strokeDasharray="4 5" strokeLinecap="round" />
      <path d="M104 30h12M110 24v12" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
    </svg>
  )
}
