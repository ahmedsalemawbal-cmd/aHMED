import * as ImagePicker from 'expo-image-picker'
import { Alert } from 'react-native'
import { decode } from 'base64-arraybuffer'
import { supabase } from './supabase'

export type PickSource = 'camera' | 'library'

const OPTS: ImagePicker.ImagePickerOptions = {
  mediaTypes: ImagePicker.MediaTypeOptions.Images,
  allowsEditing: true,
  aspect: [1, 1],          // دائريّةٌ في العرض، فالقصّ مربّعٌ من المصدر
  quality: 0.82,
  base64: true,
}

async function ensure(source: PickSource): Promise<boolean> {
  const p = source === 'camera'
    ? await ImagePicker.requestCameraPermissionsAsync()
    : await ImagePicker.requestMediaLibraryPermissionsAsync()
  if (p.granted) return true
  Alert.alert(
    source === 'camera' ? 'الكاميرا مغلقة' : 'الصور مغلقة',
    'افتح إعدادات الجهاز وامنح مِداد الإذن، ثمّ عُد.',
  )
  return false
}

/**
 * يختار صورةً ويرفعها إلى avatars/{uid}/avatar.jpg ثمّ يكتب رابطها في الملفّ الشخصيّ.
 * المسار ثابتٌ لكلّ مستخدم فلا تتراكم الصور القديمة؛ ونضيف بصمةً زمنيّة للرابط
 * كي لا يُبقي التخزين المؤقّت الصورةَ السابقة معروضة.
 */
export async function pickAndUploadAvatar(source: PickSource, uid: string): Promise<string | null> {
  if (!(await ensure(source))) return null
  const res = source === 'camera'
    ? await ImagePicker.launchCameraAsync(OPTS)
    : await ImagePicker.launchImageLibraryAsync(OPTS)
  if (res.canceled || !res.assets?.length) return null

  const asset = res.assets[0]
  if (!asset.base64) throw new Error('تعذّرت قراءة الصورة')

  const path = `${uid}/avatar.jpg`
  const { error: upErr } = await supabase.storage
    .from('avatars')
    .upload(path, decode(asset.base64), { contentType: 'image/jpeg', upsert: true })
  if (upErr) throw new Error('تعذّر رفع الصورة — تحقّق من اتّصالك')

  const { data } = supabase.storage.from('avatars').getPublicUrl(path)
  const url = `${data.publicUrl}?v=${Date.now()}`

  const { error: dbErr } = await supabase.from('profiles').update({ avatar_url: url }).eq('id', uid)
  if (dbErr) throw new Error('رُفعت الصورة ولم تُحفظ في حسابك')
  return url
}

export async function removeAvatar(uid: string): Promise<void> {
  await supabase.storage.from('avatars').remove([`${uid}/avatar.jpg`]).catch(() => {})
  const { error } = await supabase.from('profiles').update({ avatar_url: null }).eq('id', uid)
  if (error) throw new Error('تعذّر حذف الصورة')
}
