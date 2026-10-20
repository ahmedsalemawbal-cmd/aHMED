// Company contact details and site-wide settings, shared by the Arabic and English pages.

// Public address of the live site, used for canonical links, hreflang, Open Graph and the sitemap.
// Replace it with the real domain before launch, or set SITE_URL when running the build.
export const SITE_URL = (process.env.SITE_URL || 'https://shatara.example').replace(/\/+$/, '');

// Formspree form ID (the part after https://formspree.io/f/). When empty, the contact and
// request forms open WhatsApp with the form content already filled in instead.
export const FORMSPREE_ID = process.env.FORMSPREE_ID || '';

export const contact = {
  phone: '0533420302',
  whatsapp: '966533420302',
  management: ['0505627753', '0533213031'],
  email: 'shatara.recruitment@gmail.com'
};

export const whatsappUrl = `https://wa.me/${contact.whatsapp}`;

// Social accounts — still placeholders in the approved design.
export const social = [
  { label: 'Instagram', icon: 'instagram-logo', href: '#' },
  { label: 'X', icon: 'x-logo', href: '#' },
  { label: 'Snapchat', icon: 'snapchat-logo', href: '#' },
  { label: 'TikTok', icon: 'tiktok-logo', href: '#' }
];

// Countries in display order; the names come from the language files.
export const countryCodes = ['ph', 'ke', 'ug', 'et', 'bi', 'gh', 'bd', 'in', 'lk'];
