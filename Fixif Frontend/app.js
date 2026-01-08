/**
 * Fixif.ai API - app.js (Validated)
 *
 * Adds stricter, user-friendly validations across:
 * - Auth (email/password/name)
 * - Profile (businessName/logo)
 * - Customer lookup (phone normalization)
 * - Jobs create/patch/read (payload shape + status transitions + aiResult edits)
 * - Media uploads (mime/type/count/size) + safe URL generation
 * - VIN decode (VIN validation)
 *
 * Dependencies:
 *   npm i express cors openai mongoose bcryptjs jsonwebtoken multer
 *
 * Env:
 *   OPENAI_API_KEY=...
 *   OPENAI_MODEL=gpt-4.1-mini
 *   MONGODB_URI=...
 *   JWT_SECRET=...
 *   PUBLIC_BASE_URL=https://yourdomain.com   (optional)
 *   UPLOAD_DIR=./uploads                    (optional)
 *   MAX_UPLOAD_MB=12                        (optional, per file)
 */

require('dotenv').config();

const express = require('express');
const cors = require('cors');
const OpenAI = require('openai');
const mongoose = require('mongoose');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const path = require('path');
const fs = require('fs');
const multer = require('multer');

const app = express();

// --------------------------------------------------
// Express setup
// --------------------------------------------------
app.use(cors());
app.use(express.json({ limit: '2mb' }));

// --------------------------------------------------
// ENV / CONFIG
// --------------------------------------------------
const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });

const MODEL = process.env.OPENAI_MODEL || 'gpt-4.1-mini';
const PORT = Number(process.env.PORT || 4000);
const MONGODB_URI = process.env.MONGODB_URI || process.env.MONGO_URI;
const JWT_SECRET = process.env.JWT_SECRET || 'fixif-dev-secret-change-me';

const UPLOAD_DIR = process.env.UPLOAD_DIR || path.join(__dirname, 'uploads');
const MAX_UPLOAD_MB = Number(process.env.MAX_UPLOAD_MB || 12); // per file
const PUBLIC_BASE_URL = (process.env.PUBLIC_BASE_URL || '').trim().replace(/\/+$/, ''); // no trailing slash

if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

// Serve uploaded files
app.use('/uploads', express.static(UPLOAD_DIR, { maxAge: '7d', etag: true }));

// --------------------------------------------------
// Mongo Connection
// --------------------------------------------------
if (!MONGODB_URI) {
  console.warn('⚠️  MONGODB_URI/MONGO_URI is not set in .env. Skipping Mongo connection.');
} else {
  mongoose
    .connect(MONGODB_URI)
    .then(() => console.log('✅ MongoDB connected'))
    .catch((err) => console.error('❌ MongoDB connection error:', err.message));
}

// --------------------------------------------------
// Helpers (validation + sanitization)
// --------------------------------------------------
function asString(v) {
  if (v === null || v === undefined) return '';
  return String(v);
}
function cleanTrim(v, maxLen = 5000) {
  const s = asString(v).replace(/\s+/g, ' ').trim();
  if (!s) return '';
  return s.length > maxLen ? s.slice(0, maxLen) : s;
}
function cleanEmail(v) {
  return cleanTrim(v, 320).toLowerCase();
}
function normalizePhone(v) {
  const raw = asString(v).trim();
  return raw.replace(/[^\d+]/g, '');
}
function isLikelyPHPhone(v) {
  const p = normalizePhone(v);
  if (!p) return false;
  if (/^\+63\d{10}$/.test(p)) return true;
  if (/^63\d{10}$/.test(p)) return true;
  if (/^09\d{9}$/.test(p)) return true;

  // allow wider international range if user uses non-PH:
  const digits = p.replace(/[^\d]/g, '');
  return digits.length >= 8 && digits.length <= 15;
}
function isISODateOnly(s) {
  // YYYY-MM-DD
  if (!s) return false;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
  const d = new Date(s + 'T00:00:00Z');
  return !Number.isNaN(d.getTime());
}
function clampNumber(v, min, max, fallback) {
  const n = Number(v);
  if (!Number.isFinite(n)) return fallback;
  if (n < min) return min;
  if (n > max) return max;
  return n;
}
function isValidEmailFormat(email) {
  const e = cleanEmail(email);
  if (!e) return true; // optional in many places
  // basic RFC-ish check
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e) && e.length <= 320;
}
function randomId(len = 10) {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let out = '';
  for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random() * chars.length)];
  return out;
}
function isValidVin(v) {
  const s = cleanTrim(v, 32).toUpperCase();
  if (!s) return false;
  if (s.length !== 17) return false;
  if (/[IOQ]/.test(s)) return false;
  return /^[A-Z0-9]+$/.test(s);
}
function normalizeVin(v) {
  return cleanTrim(v, 32).toUpperCase();
}
function normalizePlate(v) {
  // keep user input but normalize spaces and uppercase
  return cleanTrim(v, 32).toUpperCase();
}
function safePublicUrlForUpload(filename) {
  // Always serve via /uploads; if PUBLIC_BASE_URL set, return absolute.
  const rel = '/uploads/' + encodeURIComponent(filename);
  return PUBLIC_BASE_URL ? (PUBLIC_BASE_URL + rel) : rel;
}
function isSafeUrl(u) {
  const s = cleanTrim(u, 600);
  if (!s) return false;
  // allow local uploads path OR absolute http(s)
  if (s.startsWith('/uploads/')) return true;
  if (/^https?:\/\/[^\s]+$/i.test(s)) return true;
  return false;
}

const TOP_LANGUAGES = new Set([
  'English',
  'Mandarin Chinese',
  'Hindi',
  'Spanish',
  'French',
  'Arabic',
  'Bengali',
  'Portuguese',
  'Russian',
  'Urdu',
]);

const DETAIL_LEVELS = new Set(['Detailed', 'Normal', 'Brief']);
const TONES = new Set(['More Technical', 'Balanced', 'More Friendly']);

// --------------------------------------------------
// Mongo Schemas
// --------------------------------------------------

// Users
const userSchema = new mongoose.Schema(
  {
    name: { type: String, required: true, maxlength: 120 },
    email: { type: String, required: true, unique: true, index: true, maxlength: 320 },
    passwordHash: { type: String, required: true },
  },
  { timestamps: true }
);
const User = mongoose.model('User', userSchema);

// Profile (per user)
const profileSchema = new mongoose.Schema(
  {
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, unique: true, index: true },
    businessName: { type: String, default: '', maxlength: 120 },
    logoUrl: { type: String, default: '' },
  },
  { timestamps: true }
);
const Profile = mongoose.model('Profile', profileSchema);

// Customer
const customerSchema = new mongoose.Schema(
  {
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, index: true },
    fullName: { type: String, required: true, maxlength: 200 },
    phone: { type: String, required: true, index: true },
    email: { type: String, default: '', maxlength: 320 },
    preferredContactMethod: { type: String, enum: ['text', 'email'], default: 'text' },
  },
  { timestamps: true }
);
customerSchema.index({ userId: 1, phone: 1 }, { unique: true });
const Customer = mongoose.model('Customer', customerSchema);

// Vehicle
const vehicleSchema = new mongoose.Schema(
  {
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, index: true },
    customerId: { type: mongoose.Schema.Types.ObjectId, ref: 'Customer', required: true, index: true },
    vin: { type: String, default: '', maxlength: 32 },
    plate: { type: String, default: '', maxlength: 32 },
    year: { type: String, default: '', maxlength: 10 },
    make: { type: String, default: '', maxlength: 80 },
    model: { type: String, default: '', maxlength: 120 },
    engine: { type: String, default: '', maxlength: 120 },
    transmission: { type: String, default: '', maxlength: 20 },
    dropOffDate: { type: String, default: '' }, // YYYY-MM-DD
  },
  { timestamps: true }
);
const Vehicle = mongoose.model('Vehicle', vehicleSchema);

// AI result subdocs
const aiCauseSchema = new mongoose.Schema(
  {
    title: { type: String, default: '' },
    likelihood: { type: String, enum: ['high', 'medium', 'low'], default: 'medium' },
    explanation: { type: String, default: '' },
  },
  { _id: false }
);

const aiPartSchema = new mongoose.Schema(
  {
    partName: { type: String, default: '' },
    oemOrAftermarket: { type: String, enum: ['OEM', 'aftermarket ok', 'unspecified'], default: 'unspecified' },
    urgency: { type: String, enum: ['required_before_release', 'upgrade_soon', 'optional'], default: 'optional' },
    notes: { type: String, default: '' },
  },
  { _id: false }
);

const aiResultSchema = new mongoose.Schema(
  {
    mostLikelyIssue: { type: String, default: '' },
    confidenceLevel: { type: Number, default: 0, min: 0, max: 100 },
    probableCauses: { type: [aiCauseSchema], default: [] },
    partsNeeded: { type: [aiPartSchema], default: [] },

    estimatedLaborHours: { type: Number, default: 0, min: 0 },
    additionalMechanicNotes: { type: String, default: '' },
    estimatedPickupDate: { type: String, default: '' }, // YYYY-MM-DD optional
  },
  { _id: false }
);

const jobSchema = new mongoose.Schema(
  {
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true, index: true },
    customerId: { type: mongoose.Schema.Types.ObjectId, ref: 'Customer', required: true, index: true },
    vehicleId: { type: mongoose.Schema.Types.ObjectId, ref: 'Vehicle', required: true, index: true },

    status: { type: String, enum: ['in_progress', 'completed', 'cancelled'], default: 'in_progress', index: true },
    cancelNotes: { type: String, default: '' },

    customer: {
      fullName: { type: String, default: '' },
      phone: { type: String, default: '' },
      email: { type: String, default: '' },
      preferredContactMethod: { type: String, enum: ['text', 'email'], default: 'text' },
    },

    vehicle: {
      vin: { type: String, default: '' },
      plate: { type: String, default: '' },
      year: { type: String, default: '' },
      make: { type: String, default: '' },
      model: { type: String, default: '' },
      engine: { type: String, default: '' },
      transmission: { type: String, default: '' },
      dropOffDate: { type: String, default: '' }, // YYYY-MM-DD
    },

    diagnostic: {
      obd2Data: { type: String, default: '' },
      symptoms: { type: String, default: '' },
      media: { type: [String], default: [] }, // URLs
    },

    preferences: {
      detailLevel: { type: String, default: 'Detailed' },
      language: { type: String, default: 'English' },
      tone: { type: String, default: 'More Technical' },
    },

    aiResult: { type: aiResultSchema, default: () => ({}) },
    generatedOn: { type: Date, default: null },
    aiRawText: { type: String, default: '' },
    aiModel: { type: String, default: '' },
  },
  { timestamps: true }
);

const Job = mongoose.model('Job', jobSchema);

// --------------------------------------------------
// Auth helpers
// --------------------------------------------------
function generateToken(user) {
  return jwt.sign(
    {
      id: user._id.toString(),
      email: user.email,
    },
    JWT_SECRET,
    { expiresIn: '7d' }
  );
}

function authMiddleware(req, res, next) {
  const authHeader = req.headers.authorization || '';
  const token = authHeader.startsWith('Bearer ') ? authHeader.slice(7) : null;

  if (!token) return res.status(401).json({ error: 'No token provided.' });

  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    req.user = decoded; // { id, email }
    return next();
  } catch (err) {
    return res.status(401).json({ error: 'Invalid or expired token.' });
  }
}

// --------------------------------------------------
// Upload (multer) with strict validation
// --------------------------------------------------
const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, UPLOAD_DIR),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname || '').toLowerCase().slice(0, 10);
    const safeExt = ext && /^[.a-z0-9]+$/.test(ext) ? ext : '';
    cb(null, `${Date.now()}-${randomId(10)}${safeExt}`);
  },
});

function isAllowedLogo(mime) {
  return ['image/jpeg', 'image/png', 'image/webp'].includes(mime);
}
function isAllowedMedia(mime) {
  return (
    mime.startsWith('image/') ||
    ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska'].includes(mime)
  );
}

const upload = multer({
  storage,
  limits: { fileSize: MAX_UPLOAD_MB * 1024 * 1024, files: 10 }, // hard cap for safety
  fileFilter: (req, file, cb) => {
    const isLogo = req.path === '/api/profile/logo';
    const ok = isLogo ? isAllowedLogo(file.mimetype) : isAllowedMedia(file.mimetype);
    if (!ok) {
      return cb(new Error(isLogo ? 'Invalid logo file type.' : 'Invalid media file type.'));
    }
    return cb(null, true);
  },
});

// Multer error handler
function multerErrorHandler(err, req, res, next) {
  if (!err) return next();
  const msg = asString(err.message || 'Upload error.');
  if (/file too large/i.test(msg)) return res.status(413).json({ error: `File too large. Max ${MAX_UPLOAD_MB}MB per file.` });
  return res.status(400).json({ error: msg });
}

// --------------------------------------------------
// AI prompt (compliant to your spec: no summary on top, no DIY tips)
// --------------------------------------------------
const baseInstructions = `
You are an expert automotive diagnostic AI that assists a repair shop service advisor.

OUTPUT: Return ONLY valid JSON (no markdown, no extra text) in this exact structure:

{
  "mostLikelyIssue": "Short editable diagnosis headline",
  "confidenceLevel": 0-100,
  "probableCauses": [
    {
      "title": "Short cause name",
      "likelihood": "high | medium | low",
      "explanation": "1-3 sentence explanation"
    }
  ],
  "partsNeeded": [
    {
      "partName": "Part or assembly",
      "oemOrAftermarket": "OEM | aftermarket ok | unspecified",
      "urgency": "required_before_release | upgrade_soon | optional",
      "notes": "Notes"
    }
  ],
  "estimatedLaborHours": 0,
  "additionalMechanicNotes": "",
  "estimatedPickupDate": ""
}

Rules:
- Do NOT include a summary section.
- Do NOT include immediateChecks / DIY tips.
- Provide 2–8 probableCauses when possible.
- Respect preferences.detailLevel, preferences.language, preferences.tone.
- If estimatedPickupDate unknown, return "" (empty string).
`;

// --------------------------------------------------
// Payload sanitation
// --------------------------------------------------
function sanitizePreferences(prefs) {
  const detail = cleanTrim(prefs?.detailLevel, 40) || 'Detailed';
  const lang = cleanTrim(prefs?.language, 60) || 'English';
  const tone = cleanTrim(prefs?.tone, 60) || 'More Technical';

  // allow only known values; fallback to defaults
  const detailLevel = DETAIL_LEVELS.has(detail) ? detail : 'Detailed';
  const language = TOP_LANGUAGES.has(lang) ? lang : 'English';
  const toneFinal = TONES.has(tone) ? tone : 'More Technical';

  return { detailLevel, language, tone: toneFinal };
}

function sanitizeCustomerInput(customer) {
  const fullName = cleanTrim(customer?.fullName, 200);
  const phone = normalizePhone(customer?.phone);
  const email = cleanEmail(customer?.email);
  const preferredContactMethod = customer?.preferredContactMethod === 'email' ? 'email' : 'text';

  return { fullName, phone, email, preferredContactMethod };
}

function sanitizeVehicleInput(vehicle) {
  const vin = normalizeVin(vehicle?.vin);
  const plate = normalizePlate(vehicle?.plate);
  const year = cleanTrim(vehicle?.year, 10);
  const make = cleanTrim(vehicle?.make, 80);
  const model = cleanTrim(vehicle?.model, 120);
  const engine = cleanTrim(vehicle?.engine, 120);
  const transmission = cleanTrim(vehicle?.transmission, 20);
  const dropOffDate = cleanTrim(vehicle?.dropOffDate, 10);

  return { vin, plate, year, make, model, engine, transmission, dropOffDate };
}

function sanitizeDiagnosticInput(diagnostic) {
  const obd2Data = cleanTrim(diagnostic?.obd2Data, 12000);
  const symptoms = cleanTrim(diagnostic?.symptoms, 12000);

  const media = Array.isArray(diagnostic?.media)
    ? diagnostic.media.map((m) => cleanTrim(m, 600)).filter(Boolean).slice(0, 25)
    : [];

  return { obd2Data, symptoms, media };
}

function sanitizePartsNeeded(parts) {
  if (parts === null || parts === undefined) return [];
  if (!Array.isArray(parts)) return null;

  const out = [];
  for (const raw of parts) {
    if (!raw || typeof raw !== 'object') continue;
    const partName = cleanTrim(raw.partName, 120);
    if (!partName) continue;

    const urgencyRaw = cleanTrim(raw.urgency, 40);
    const urgency = ['required_before_release', 'upgrade_soon', 'optional'].includes(urgencyRaw)
      ? urgencyRaw
      : 'optional';

    const oemRaw = cleanTrim(raw.oemOrAftermarket, 40);
    const oemOrAftermarket = ['OEM', 'aftermarket ok', 'unspecified'].includes(oemRaw)
      ? oemRaw
      : 'unspecified';

    const notes = cleanTrim(raw.notes, 300);

    out.push({ partName, urgency, oemOrAftermarket, notes });
  }
  return out.slice(0, 40);
}

function validateJobCreatePayload(p) {
  const errors = [];

  // customer validation
  if (!p.customer.phone) errors.push('customer.phone is required.');
  if (p.customer.phone && !isLikelyPHPhone(p.customer.phone)) errors.push('customer.phone must be a valid phone number.');
  if (p.customer.fullName && p.customer.fullName.length < 2) errors.push('customer.fullName must be at least 2 characters if provided.');
  if (!isValidEmailFormat(p.customer.email)) errors.push('customer.email is invalid.');

  // vehicle validation
  if (p.vehicle.vin && !isValidVin(p.vehicle.vin)) errors.push('vehicle.vin must be a valid 17-character VIN (no I/O/Q).');
  if (p.vehicle.dropOffDate && !isISODateOnly(p.vehicle.dropOffDate)) errors.push('vehicle.dropOffDate must be YYYY-MM-DD.');
  if (p.vehicle.year) {
    const y = Number(p.vehicle.year);
    if (!Number.isFinite(y) || y < 1950 || y > new Date().getFullYear() + 1) {
      errors.push('vehicle.year must be a valid year (1950..next year).');
    }
  }

  // diagnostic validation
  if (!p.diagnostic.symptoms) errors.push('diagnostic.symptoms is required.');
  if (p.diagnostic.symptoms && p.diagnostic.symptoms.length < 6) errors.push('diagnostic.symptoms must be at least 6 characters.');
  if (p.diagnostic.media && !Array.isArray(p.diagnostic.media)) errors.push('diagnostic.media must be an array of URLs.');
  if (Array.isArray(p.diagnostic.media)) {
    for (const u of p.diagnostic.media) {
      if (!isSafeUrl(u)) errors.push('diagnostic.media contains an invalid URL.');
    }
  }

  // preferences validation (already normalized)
  if (!DETAIL_LEVELS.has(p.preferences.detailLevel)) errors.push('preferences.detailLevel is invalid.');
  if (!TOP_LANGUAGES.has(p.preferences.language)) errors.push('preferences.language must be one of the top 10 languages.');
  if (!TONES.has(p.preferences.tone)) errors.push('preferences.tone is invalid.');

  return errors;
}

function validateStatusTransition(current, next) {
  if (current === next) return { ok: true };
  if (current === 'completed' || current === 'cancelled') {
    return { ok: false, error: `Status is final (${current}) and cannot be changed.` };
  }
  if (current === 'in_progress' && (next === 'completed' || next === 'cancelled')) {
    return { ok: true };
  }
  return { ok: false, error: 'Invalid status transition.' };
}

// --------------------------------------------------
// Routes
// --------------------------------------------------
app.get('/health', (req, res) => {
  return res.json({ status: 'ok', model: MODEL });
});

// --------------------
// Auth
// --------------------
app.post('/api/auth/register', async (req, res) => {
  try {
    const name = cleanTrim(req.body?.name, 120);
    const email = cleanEmail(req.body?.email);
    const password = asString(req.body?.password || '');

    const errors = [];
    if (!name || name.length < 2) errors.push('Name is required (min 2 chars).');
    if (!email || !isValidEmailFormat(email)) errors.push('Valid email is required.');
    if (!password || password.length < 8) errors.push('Password must be at least 8 characters.');

    // basic strength
    if (password && password.length >= 8) {
      const hasLower = /[a-z]/.test(password);
      const hasUpper = /[A-Z]/.test(password);
      const hasDigit = /\d/.test(password);
      if (!(hasLower && hasUpper && hasDigit)) {
        errors.push('Password must include uppercase, lowercase, and a number.');
      }
    }

    if (errors.length) return res.status(400).json({ error: 'Validation failed.', details: errors });

    const existing = await User.findOne({ email });
    if (existing) return res.status(409).json({ error: 'Email is already registered.' });

    const passwordHash = await bcrypt.hash(password, 10);

    const user = await User.create({ name, email, passwordHash });

    // create profile stub
    await Profile.updateOne(
      { userId: user._id },
      { $setOnInsert: { userId: user._id, businessName: name, logoUrl: '' } },
      { upsert: true }
    );

    const token = generateToken(user);

    return res.status(201).json({
      token,
      user: { id: user._id, name: user.name, email: user.email },
    });
  } catch (err) {
    console.error('🔥 [AUTH ERROR] /api/auth/register', err);
    return res.status(500).json({ error: 'Failed to register user.', details: err.message });
  }
});

app.post('/api/auth/login', async (req, res) => {
  try {
    const email = cleanEmail(req.body?.email);
    const password = asString(req.body?.password || '');

    const errors = [];
    if (!email || !isValidEmailFormat(email)) errors.push('Valid email is required.');
    if (!password) errors.push('Password is required.');
    if (errors.length) return res.status(400).json({ error: 'Validation failed.', details: errors });

    const user = await User.findOne({ email });
    if (!user) return res.status(401).json({ error: 'Invalid email or password.' });

    const ok = await bcrypt.compare(password, user.passwordHash);
    if (!ok) return res.status(401).json({ error: 'Invalid email or password.' });

    const token = generateToken(user);

    return res.json({
      token,
      user: { id: user._id, name: user.name, email: user.email },
    });
  } catch (err) {
    console.error('🔥 [AUTH ERROR] /api/auth/login', err);
    return res.status(500).json({ error: 'Failed to log in.', details: err.message });
  }
});

app.get('/api/auth/me', authMiddleware, async (req, res) => {
  try {
    const user = await User.findById(req.user.id).select('name email');
    if (!user) return res.status(404).json({ error: 'User not found.' });

    return res.json({
      user: { id: user._id, name: user.name, email: user.email },
    });
  } catch (err) {
    console.error('🔥 [AUTH ERROR] /api/auth/me', err);
    return res.status(500).json({ error: 'Failed to load user.', details: err.message });
  }
});

// --------------------
// Profile
// --------------------
app.get('/api/profile', authMiddleware, async (req, res) => {
  try {
    const profile = await Profile.findOne({ userId: req.user.id }).lean();
    return res.json({
      profile: profile
        ? {
            businessName: profile.businessName || '',
            logoUrl: profile.logoUrl || '',
          }
        : { businessName: '', logoUrl: '' },
    });
  } catch (err) {
    console.error('🔥 [PROFILE ERROR] GET /api/profile', err);
    return res.status(500).json({ error: 'Failed to load profile.', details: err.message });
  }
});

app.patch('/api/profile', authMiddleware, async (req, res) => {
  try {
    const businessName = cleanTrim(req.body?.businessName, 120);

    if (!businessName || businessName.length < 2) {
      return res.status(400).json({ error: 'businessName must be at least 2 characters.' });
    }

    const updated = await Profile.findOneAndUpdate(
      { userId: req.user.id },
      { $set: { businessName } },
      { upsert: true, new: true }
    ).lean();

    return res.json({
      profile: { businessName: updated.businessName || '', logoUrl: updated.logoUrl || '' },
    });
  } catch (err) {
    console.error('🔥 [PROFILE ERROR] PATCH /api/profile', err);
    return res.status(500).json({ error: 'Failed to update profile.', details: err.message });
  }
});

app.post('/api/profile/logo', authMiddleware, upload.single('logo'), multerErrorHandler, async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'logo file is required.' });

    if (!isAllowedLogo(req.file.mimetype)) {
      // Should already be blocked by fileFilter, but keep defense-in-depth
      return res.status(400).json({ error: 'Invalid logo file type.' });
    }

    const logoUrl = safePublicUrlForUpload(req.file.filename);

    const updated = await Profile.findOneAndUpdate(
      { userId: req.user.id },
      { $set: { logoUrl } },
      { upsert: true, new: true }
    ).lean();

    return res.json({ success: true, logoUrl: updated.logoUrl || logoUrl });
  } catch (err) {
    console.error('🔥 [PROFILE ERROR] POST /api/profile/logo', err);
    return res.status(500).json({ error: 'Failed to upload logo.', details: err.message });
  }
});

// --------------------
// Media upload
// --------------------
app.post('/api/media/upload', authMiddleware, upload.array('media', 8), multerErrorHandler, async (req, res) => {
  try {
    const files = Array.isArray(req.files) ? req.files : [];
    if (!files.length) return res.status(400).json({ error: 'No media files uploaded.' });

    // validate mimetypes (defense-in-depth)
    for (const f of files) {
      if (!isAllowedMedia(f.mimetype)) {
        return res.status(400).json({ error: 'Invalid media file type detected.' });
      }
    }

    const urls = files.map((f) => safePublicUrlForUpload(f.filename));
    return res.json({ success: true, urls });
  } catch (err) {
    console.error('🔥 [MEDIA ERROR] POST /api/media/upload', err);
    return res.status(500).json({ error: 'Failed to upload media.', details: err.message });
  }
});

// --------------------
// VIN decode (db.vin no key; NHTSA fallback)
// --------------------
function pickNHTSAValue(nhtsaJson, variable) {
  const results = nhtsaJson?.Results;
  if (!Array.isArray(results)) return '';
  const row = results.find((x) => x?.Variable === variable);
  return cleanTrim(row?.Value, 120);
}

function mapTransmissionFromNHTSA(nhtsaJson) {
  // NHTSA doesn't always provide explicit "Transmission" variable.
  // Try common fields; keep best effort.
  const direct = pickNHTSAValue(nhtsaJson, 'Transmission Style') || pickNHTSAValue(nhtsaJson, 'Transmission Speeds');
  return cleanTrim(direct, 40);
}

app.get('/api/vin/decode', authMiddleware, async (req, res) => {
  try {
    const vin = normalizeVin(req.query?.vin);
    if (!vin) return res.status(400).json({ error: 'vin is required.' });
    if (!isValidVin(vin)) return res.status(400).json({ error: 'Invalid VIN format. VIN must be 17 chars; no I/O/Q.' });

    const dbvinUrl = `https://db.vin/api/v1/vin/${encodeURIComponent(vin)}`;
    const nhtsaUrl = `https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/${encodeURIComponent(vin)}?format=json`;

    const [dbvin, nhtsa] = await Promise.allSettled([
      fetch(dbvinUrl, { method: 'GET' }),
      fetch(nhtsaUrl, { method: 'GET' }),
    ]);

    const out = { year: '', make: '', model: '', engine: '', transmission: '' };

    if (dbvin.status === 'fulfilled' && dbvin.value.ok) {
      const j = await dbvin.value.json().catch(() => null);
      // db.vin schema: best effort mapping
      // many responses include: year/make/model/engine/transmission
      const src = j?.data || j || {};
      out.year = cleanTrim(src?.year, 10) || out.year;
      out.make = cleanTrim(src?.make, 80) || out.make;
      out.model = cleanTrim(src?.model, 120) || out.model;
      out.engine = cleanTrim(src?.engine, 120) || out.engine;
      out.transmission = cleanTrim(src?.transmission, 40) || out.transmission;
    }

    if (nhtsa.status === 'fulfilled' && nhtsa.value.ok) {
      const j = await nhtsa.value.json().catch(() => null);
      const modelYear = pickNHTSAValue(j, 'ModelYear');
      const make = pickNHTSAValue(j, 'Make');
      const model = pickNHTSAValue(j, 'Model');
      const engine = [pickNHTSAValue(j, 'EngineModel'), pickNHTSAValue(j, 'DisplacementL')].filter(Boolean).join(' ').trim();
      const transmission = mapTransmissionFromNHTSA(j);

      if (!out.year && modelYear) out.year = modelYear;
      if (!out.make && make) out.make = make;
      if (!out.model && model) out.model = model;
      if (engine) out.engine = engine;
      if (transmission && !out.transmission) out.transmission = transmission;
    }

    return res.json({ success: true, decoded: out, sources: { dbvin: dbvin.status, nhtsa: nhtsa.status } });
  } catch (err) {
    console.error('🔥 [VIN ERROR] /api/vin/decode', err);
    return res.status(500).json({ error: 'Failed to decode VIN.', details: err.message });
  }
});

// --------------------
// Customer lookup
// --------------------
app.get('/api/customers/lookup', authMiddleware, async (req, res) => {
  try {
    const phone = normalizePhone(req.query?.phone);
    if (!phone) return res.status(400).json({ error: 'phone is required.' });
    if (!isLikelyPHPhone(phone)) return res.status(400).json({ error: 'Invalid phone number format.' });

    const userId = req.user.id;

    const customer = await Customer.findOne({ userId, phone }).lean();
    if (!customer) return res.json({ found: false });

    const vehicles = await Vehicle.find({ userId, customerId: customer._id })
      .sort({ updatedAt: -1 })
      .limit(25)
      .lean();

    const jobs = await Job.find({ userId, customerId: customer._id })
      .sort({ createdAt: -1 })
      .limit(25)
      .select('status createdAt updatedAt aiResult.mostLikelyIssue aiResult.confidenceLevel vehicleId cancelNotes')
      .lean();

    return res.json({
      found: true,
      customer: {
        id: customer._id,
        fullName: customer.fullName,
        phone: customer.phone,
        email: customer.email,
        preferredContactMethod: customer.preferredContactMethod,
      },
      vehicles: vehicles.map((v) => ({
        id: v._id,
        vin: v.vin,
        plate: v.plate,
        year: v.year,
        make: v.make,
        model: v.model,
        engine: v.engine,
        transmission: v.transmission,
        dropOffDate: v.dropOffDate,
        updatedAt: v.updatedAt,
      })),
      history: jobs.map((j) => ({
        id: j._id,
        status: j.status,
        createdAt: j.createdAt,
        updatedAt: j.updatedAt,
        mostLikelyIssue: j.aiResult?.mostLikelyIssue || '',
        confidenceLevel: j.aiResult?.confidenceLevel ?? 0,
        vehicleId: j.vehicleId,
        cancelNotes: cleanTrim(j.cancelNotes, 600),
      })),
    });
  } catch (err) {
    console.error('🔥 [LOOKUP ERROR] /api/customers/lookup', err);
    return res.status(500).json({ error: 'Lookup failed.', details: err.message });
  }
});

// --------------------
// AI generation
// --------------------
async function generateAIDiagnosis(payload) {
  if (!process.env.OPENAI_API_KEY) {
    throw new Error('OPENAI_API_KEY is missing on the server.');
  }

  const completion = await client.chat.completions.create({
    model: MODEL,
    response_format: { type: 'json_object' },
    messages: [
      { role: 'system', content: baseInstructions },
      {
        role: 'user',
        content:
          'Here is the intake payload as JSON. Generate ONLY the diagnosis JSON object as specified.\n\n' +
          JSON.stringify(payload, null, 2),
      },
    ],
  });

  const rawText = completion.choices?.[0]?.message?.content?.trim() || '';
  if (!rawText) throw new Error('AI returned an empty response.');

  let parsed;
  try {
    parsed = JSON.parse(rawText);
  } catch {
    // return safe fallback
    return { safe: null, rawText };
  }

  // Convert model output into our schema (sanitize)
  const safe = {
    mostLikelyIssue: cleanTrim(parsed?.mostLikelyIssue, 200),
    confidenceLevel: clampNumber(parsed?.confidenceLevel, 0, 100, 0),
    probableCauses: Array.isArray(parsed?.probableCauses)
      ? parsed.probableCauses
          .slice(0, 12)
          .map((c) => ({
            title: cleanTrim(c?.title, 120),
            likelihood: ['high', 'medium', 'low'].includes(cleanTrim(c?.likelihood, 10))
              ? cleanTrim(c?.likelihood, 10)
              : 'medium',
            explanation: cleanTrim(c?.explanation, 800),
          }))
          .filter((c) => c.title)
      : [],
    partsNeeded: sanitizePartsNeeded(parsed?.partsNeeded) || [],
    estimatedLaborHours: clampNumber(parsed?.estimatedLaborHours, 0, 999, 0),
    additionalMechanicNotes: '', // blank default per spec
    estimatedPickupDate: isISODateOnly(cleanTrim(parsed?.estimatedPickupDate, 10)) ? cleanTrim(parsed?.estimatedPickupDate, 10) : '',
  };

  return { safe, rawText };
}

// --------------------
// Jobs create
// --------------------
app.post('/api/jobs', authMiddleware, async (req, res) => {
  try {
    const customer = sanitizeCustomerInput(req.body?.customer || {});
    const vehicle = sanitizeVehicleInput(req.body?.vehicle || {});
    const diagnostic = sanitizeDiagnosticInput(req.body?.diagnostic || {});
    const preferences = sanitizePreferences(req.body?.preferences || {});

    // normalize URL list to safe values only
    diagnostic.media = diagnostic.media.filter((u) => isSafeUrl(u)).slice(0, 25);

    const payload = { customer, vehicle, diagnostic, preferences };

    const errors = validateJobCreatePayload(payload);
    if (errors.length) return res.status(400).json({ error: 'Validation failed.', details: errors });

    // upsert customer by phone (per user)
    const userId = req.user.id;

    const customerDoc = await Customer.findOneAndUpdate(
      { userId, phone: customer.phone },
      {
        $set: {
          fullName: customer.fullName || '—',
          email: customer.email || '',
          preferredContactMethod: customer.preferredContactMethod,
        },
        $setOnInsert: { userId, phone: customer.phone },
      },
      { upsert: true, new: true }
    );

    // create vehicle record
    const vehicleDoc = await Vehicle.create({
      userId,
      customerId: customerDoc._id,
      vin: vehicle.vin,
      plate: vehicle.plate,
      year: vehicle.year,
      make: vehicle.make,
      model: vehicle.model,
      engine: vehicle.engine,
      transmission: vehicle.transmission,
      dropOffDate: vehicle.dropOffDate && isISODateOnly(vehicle.dropOffDate) ? vehicle.dropOffDate : '',
    });

    // AI
    const { safe, rawText } = await generateAIDiagnosis({
      customer,
      vehicle,
      complaint: { symptoms: diagnostic.symptoms, obd2Data: diagnostic.obd2Data, media: diagnostic.media },
      preferences,
    });

    if (!safe) {
      return res.status(502).json({
        error: 'AI did not return valid JSON.',
        rawText,
      });
    }

    const job = await Job.create({
      userId,
      customerId: customerDoc._id,
      vehicleId: vehicleDoc._id,
      status: 'in_progress',
      cancelNotes: '',
      customer,
      vehicle,
      diagnostic,
      preferences,
      aiResult: safe,
      generatedOn: new Date(),
      aiRawText: rawText,
      aiModel: MODEL,
    });

    return res.json({ success: true, job });
  } catch (err) {
    console.error('🔥 [JOB CREATE ERROR] /api/jobs', err);
    return res.status(500).json({ error: 'Failed to create job.', details: err.message });
  }
});
app.post('/api/jobs/:id/summary/customer', authMiddleware, async (req, res) => {
  const job = await Job.findOne({ _id: req.params.id, userId: req.user.id });
  if (!job) return res.status(404).json({ error: 'Job not found' });

  const summary = `
Vehicle: ${job.vehicle.year} ${job.vehicle.make} ${job.vehicle.model}
Issue: ${job.aiResult.mostLikelyIssue}
Confidence: ${job.aiResult.confidenceLevel}%

Estimated Labor: ${job.aiResult.estimatedLaborHours} hrs
Estimated Pickup: ${job.aiResult.estimatedPickupDate || 'TBD'}

Notes:
${job.aiResult.additionalMechanicNotes || '—'}
`.trim();

  res.json({ summary });
});


app.post('/api/jobs/:id/summary/mechanic', authMiddleware, async (req, res) => {
  const job = await Job.findOne({ _id: req.params.id, userId: req.user.id });
  if (!job) return res.status(404).json({ error: 'Job not found' });

  res.json({
    summary: {
      customer: job.customer,
      vehicle: job.vehicle,
      diagnostic: job.diagnostic,
      aiResult: job.aiResult,
      status: job.status,
      cancelNotes: job.cancelNotes
    }
  });
});


// --------------------
// Jobs patch (save edits + status + cancel notes + media URL edits)
// --------------------
app.patch('/api/jobs/:id', authMiddleware, async (req, res) => {
  try {
    const job = await Job.findOne({ _id: req.params.id, userId: req.user.id });
    if (!job) return res.status(404).json({ error: 'Job not found.' });

    // status updates
    if (req.body?.status !== undefined) {
      const nextStatus = cleanTrim(req.body.status, 40);
      if (!['in_progress', 'completed', 'cancelled'].includes(nextStatus)) {
        return res.status(400).json({ error: 'Invalid status.' });
      }

      const check = validateStatusTransition(job.status, nextStatus);
      if (!check.ok) return res.status(400).json({ error: check.error });

      job.status = nextStatus;

      if (nextStatus === 'cancelled') {
        const notes = cleanTrim(req.body?.cancelNotes, 1000);
        job.cancelNotes = notes;
      }

      // If completed, keep cancelNotes empty
      if (nextStatus === 'completed') job.cancelNotes = '';
    }

    // aiResult edits
    if (req.body?.aiResult && typeof req.body.aiResult === 'object') {
      const r = req.body.aiResult;

      if (r.mostLikelyIssue !== undefined) job.aiResult.mostLikelyIssue = cleanTrim(r.mostLikelyIssue, 200);

      if (r.confidenceLevel !== undefined)
        job.aiResult.confidenceLevel = clampNumber(r.confidenceLevel, 0, 100, job.aiResult.confidenceLevel || 0);

      if (r.estimatedLaborHours !== undefined)
        job.aiResult.estimatedLaborHours = clampNumber(r.estimatedLaborHours, 0, 999, job.aiResult.estimatedLaborHours || 0);

      if (r.additionalMechanicNotes !== undefined)
        job.aiResult.additionalMechanicNotes = cleanTrim(r.additionalMechanicNotes, 8000);

      if (r.estimatedPickupDate !== undefined) {
        const d = cleanTrim(r.estimatedPickupDate, 10);
        job.aiResult.estimatedPickupDate = d && isISODateOnly(d) ? d : '';
      }

      // partsNeeded editable
      if (r.partsNeeded !== undefined) {
        const sanitized = sanitizePartsNeeded(r.partsNeeded);
        if (sanitized === null) return res.status(400).json({ error: 'aiResult.partsNeeded must be an array.' });
        job.aiResult.partsNeeded = sanitized;
      }
    }

    // diagnostic media edits
    if (req.body?.diagnostic && typeof req.body.diagnostic === 'object') {
      const d = req.body.diagnostic;

      if (d.media !== undefined) {
        if (!Array.isArray(d.media)) return res.status(400).json({ error: 'diagnostic.media must be an array of URLs.' });
        const list = d.media.map((m) => cleanTrim(m, 600)).filter(Boolean);
        for (const u of list) {
          if (!isSafeUrl(u)) return res.status(400).json({ error: 'diagnostic.media contains an invalid URL.' });
        }
        job.diagnostic.media = list.slice(0, 25);
      }
    }

    await job.save();
    return res.json({ success: true, job });
  } catch (err) {
    console.error('🔥 [JOB PATCH ERROR] /api/jobs/:id', err);
    return res.status(500).json({ error: 'Failed to update job.', details: err.message });
  }
});

// Optional: read job (useful for history click-through)
app.get('/api/jobs/:id', authMiddleware, async (req, res) => {
  try {
    const job = await Job.findOne({ _id: req.params.id, userId: req.user.id }).lean();
    if (!job) return res.status(404).json({ error: 'Job not found.' });
    return res.json({ job });
  } catch (err) {
    console.error('🔥 [JOB GET ERROR] /api/jobs/:id', err);
    return res.status(500).json({ error: 'Failed to load job.', details: err.message });
  }
});

// --------------------------------------------------
// Start
// --------------------------------------------------
app.listen(PORT, () => {
  console.log(`🚗 Fixif.ai API listening on port ${PORT}`);
});