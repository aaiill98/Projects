import os
import numpy as np
import sqlite3
import uuid
import math
import cv2
import base64
import json
import mimetypes
from datetime import datetime
import time
from threading import Lock
from functools import wraps
from werkzeug.utils import secure_filename
from werkzeug.security import generate_password_hash, check_password_hash
from flask import (
    Flask, render_template, request, redirect, url_for, Response,
    session, flash, jsonify, g
)

app = Flask(__name__)
app.secret_key = 'nomu-secret-key-2025'
app.config['UPLOAD_FOLDER'] = os.path.join(app.static_folder, 'uploads')
app.config['RESULTS_FOLDER'] = os.path.join(app.static_folder, 'results')
app.config['MAX_CONTENT_LENGTH'] = 50 * 1024 * 1024
app.config['DATABASE'] = os.path.join(os.path.dirname(__file__), 'nomu.db')

ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'webp'}

os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
os.makedirs(app.config['RESULTS_FOLDER'], exist_ok=True)

# ─── YOLO Model ───
model = None
model_loaded = False

def load_model():
    global model, model_loaded
    if model_loaded:
        return model
    model_loaded = True
    try:
        from ultralytics import YOLO
        model_path = os.path.join(os.path.dirname(__file__), 'best.pt')
        if os.path.exists(model_path):
            model = YOLO(model_path)

            print("✅ YOLO model loaded successfully")
        else:
            print("⚠️ best.pt not found - AI prediction will not work")
    except Exception as e:
        print(f"⚠️ Could not load YOLO model: {e}")
    return model

def get_model():
    global model
    if model is None:
        return load_model()
    return model

# ─── SVM RIPENESS MODEL ───
import joblib

svm_model = None
svm_scaler = None

def load_svm_model():
    global svm_model, svm_scaler

    try:
        svm_model = joblib.load("svm_model.pkl")
        svm_scaler = joblib.load("scaler.pkl")

        print("✅ SVM ripeness model loaded")

    except Exception as e:
        print(f"⚠️ Could not load SVM model: {e}")

load_svm_model()
# -----------------------------
# HSV FEATURE EXTRACTION
# -----------------------------
def extract_hsv_features(img):

    img = cv2.resize(img, (64, 64))

    hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)

    h = hsv[:, :, 0]
    s = hsv[:, :, 1]
    v = hsv[:, :, 2]

    features = [
        np.mean(h), np.mean(s), np.mean(v),
        np.std(h), np.std(s), np.std(v),
        np.min(h), np.min(s), np.min(v),
        np.max(h), np.max(s), np.max(v)
    ]

    return np.array(features, dtype=np.float32)

# -----------------------------
# SVM RIPENESS PREDICTION
# -----------------------------
def predict_ripeness(crop_img):
    global svm_model, svm_scaler
    if svm_model is None or svm_scaler is None:
        return "unknown", 0

    try:
        features = extract_hsv_features(crop_img)
        features = svm_scaler.transform([features])
        pred = svm_model.predict(features)[0]
        probs = svm_model.predict_proba(features)[0]
        ripe_prob = probs[1] * 100
        if pred == 1 and ripe_prob > 50:
            return "ripe", ripe_prob
        else:
            return "unripe", ripe_prob

    except Exception as e:
        print("SVM prediction error:", e)
        return "unknown", 0
    
# ─── Database ───
def get_db():
    if 'db' not in g:
        g.db = sqlite3.connect(app.config['DATABASE'])
        g.db.row_factory = sqlite3.Row
    return g.db

@app.teardown_appcontext
def close_db(exception):
    db = g.pop('db', None)
    if db is not None:
        db.close()

def init_db():
    db = sqlite3.connect(app.config['DATABASE'])
    db.executescript('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            phone TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('farmer','volunteer')),
            location TEXT DEFAULT '',
            skills TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS harvest_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            farmer_id INTEGER NOT NULL,
            crop TEXT NOT NULL,
            harvest_date TEXT NOT NULL,
            location TEXT NOT NULL,
            volunteers_needed INTEGER DEFAULT 1,
            reward TEXT DEFAULT '',
            description TEXT DEFAULT '',
            image TEXT DEFAULT '',
            status TEXT DEFAULT 'مفتوح',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (farmer_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            request_id INTEGER NOT NULL,
            volunteer_id INTEGER NOT NULL,
            status TEXT DEFAULT 'قيد الانتظار',
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES harvest_requests(id),
            FOREIGN KEY (volunteer_id) REFERENCES users(id),
            UNIQUE(request_id, volunteer_id)
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            request_id INTEGER,
            content TEXT NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id),
            FOREIGN KEY (receiver_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rater_id INTEGER NOT NULL,
            rated_id INTEGER NOT NULL,
            request_id INTEGER NOT NULL,
            score INTEGER NOT NULL CHECK(score BETWEEN 1 AND 5),
            comment TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (rater_id) REFERENCES users(id),
            FOREIGN KEY (rated_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS predictions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            farmer_id INTEGER NOT NULL,
            image_path TEXT NOT NULL,
            result_path TEXT,
            fruit_count INTEGER DEFAULT 0,
            estimated_yield REAL DEFAULT 0,
            volunteers_recommended INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (farmer_id) REFERENCES users(id)
        );
    ''')
    # Migration: add image column if missing
    try:
        db.execute("SELECT image FROM harvest_requests LIMIT 1")
    except sqlite3.OperationalError:
        db.execute("ALTER TABLE harvest_requests ADD COLUMN image TEXT DEFAULT ''")
        db.commit()
    # Migration: add wrong_fruit_check user setting (default ON)
    try:
        db.execute("SELECT wrong_fruit_check FROM users LIMIT 1")
    except sqlite3.OperationalError:
        db.execute("ALTER TABLE users ADD COLUMN wrong_fruit_check INTEGER DEFAULT 1")
        db.commit()
    db.close()

# ─── Auth helpers ───
def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if 'user_id' not in session:
            flash('يرجى تسجيل الدخول أولاً', 'error')
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated

def farmer_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if session.get('role') != 'farmer':
            flash('هذه الصفحة للمزارعين فقط', 'error')
            return redirect(url_for('home'))
        return f(*args, **kwargs)
    return decorated

def volunteer_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if session.get('role') != 'volunteer':
            flash('هذه الصفحة للمتطوعين فقط', 'error')
            return redirect(url_for('home'))
        return f(*args, **kwargs)
    return decorated

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def get_user():
    if 'user_id' in session:
        db = get_db()
        return db.execute("SELECT * FROM users WHERE id=?", (session['user_id'],)).fetchone()
    return None

@app.context_processor
def inject_user():
    return dict(current_user=get_user())

# ─── Public Routes ───
@app.route('/')
def home():
    user = get_user()
    db = get_db()
    latest = db.execute("""
        SELECT hr.*, u.name as farmer_name, u.location as farmer_location
        FROM harvest_requests hr JOIN users u ON hr.farmer_id=u.id
        WHERE hr.status='مفتوح' ORDER BY hr.created_at DESC LIMIT 6
    """).fetchall()

    if user:
        if user['role'] == 'farmer':
            return render_template('farmer_home.html', latest_opportunities=latest)
        else:
            return render_template('volunteer_home.html', latest_opportunities=latest)
    return render_template('home.html', latest_opportunities=latest)

@app.route('/how-it-works')
def how_it_works():
    return render_template('how_it_works.html')

@app.route('/about')
def about():
    return render_template('about.html')

@app.route('/opportunities')
def opportunities():
    db = get_db()
    requests = db.execute("""
        SELECT hr.*, u.name as farmer_name, u.location as farmer_location,
        (SELECT COUNT(*) FROM applications WHERE request_id=hr.id) as applicant_count
        FROM harvest_requests hr JOIN users u ON hr.farmer_id=u.id
        WHERE hr.status='مفتوح' ORDER BY hr.created_at DESC
    """).fetchall()
    return render_template('opportunities.html', requests=requests)

# ─── Auth Routes ───
@app.route('/login', methods=['GET','POST'])
def login():
    if request.method == 'POST':
        email = request.form.get('email','').strip()
        password = request.form.get('password','')
        db = get_db()
        user = db.execute("SELECT * FROM users WHERE email=?", (email,)).fetchone()
        if user and check_password_hash(user['password'], password):
            session['user_id'] = user['id']
            session['role'] = user['role']
            session['name'] = user['name']
            if user['role'] == 'farmer':
                return redirect(url_for('farmer_dashboard'))
            else:
                return redirect(url_for('volunteer_dashboard'))
        flash('البريد الإلكتروني أو كلمة المرور غير صحيحة', 'error')
    return render_template('login.html')

@app.route('/register', methods=['GET','POST'])
def register():
    return render_template('register.html')

@app.route('/register/farmer', methods=['GET','POST'])
def register_farmer():
    if request.method == 'POST':
        name = request.form.get('name','').strip()
        phone = request.form.get('phone','').strip()
        location = request.form.get('location','').strip()
        email = request.form.get('email','').strip()
        password = request.form.get('password','')
        if not all([name, phone, email, password]):
            flash('يرجى ملء جميع الحقول المطلوبة', 'error')
            return render_template('register_farmer.html')
        db = get_db()
        existing = db.execute("SELECT id FROM users WHERE email=?", (email,)).fetchone()
        if existing:
            flash('البريد الإلكتروني مستخدم بالفعل', 'error')
            return render_template('register_farmer.html')
        db.execute("INSERT INTO users (name,email,password,phone,role,location) VALUES (?,?,?,?,?,?)",
                   (name, email, generate_password_hash(password), phone, 'farmer', location))
        db.commit()
        flash('تم إنشاء حسابك بنجاح! يرجى تسجيل الدخول.', 'success')
        return redirect(url_for('login'))
    return render_template('register_farmer.html')

@app.route('/register/volunteer', methods=['GET','POST'])
def register_volunteer():
    if request.method == 'POST':
        name = request.form.get('name','').strip()
        phone = request.form.get('phone','').strip()
        skills = request.form.get('skills','').strip()
        email = request.form.get('email','').strip()
        password = request.form.get('password','')
        if not all([name, phone, email, password]):
            flash('يرجى ملء جميع الحقول المطلوبة', 'error')
            return render_template('register_volunteer.html')
        db = get_db()
        existing = db.execute("SELECT id FROM users WHERE email=?", (email,)).fetchone()
        if existing:
            flash('البريد الإلكتروني مستخدم بالفعل', 'error')
            return render_template('register_volunteer.html')
        db.execute("INSERT INTO users (name,email,password,phone,role,skills) VALUES (?,?,?,?,?,?)",
                   (name, email, generate_password_hash(password), phone, 'volunteer', skills))
        db.commit()
        flash('تم إنشاء حسابك بنجاح! يرجى تسجيل الدخول.', 'success')
        return redirect(url_for('login'))
    return render_template('register_volunteer.html')

@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('home'))

# ─── Farmer Routes ───
@app.route('/farmer/settings', methods=['GET','POST'])
@login_required
@farmer_required
def farmer_settings():
    db = get_db()
    if request.method == 'POST':
        wrong_fruit_check = 1 if request.form.get('wrong_fruit_check') == 'on' else 0
        db.execute("UPDATE users SET wrong_fruit_check=? WHERE id=?", (wrong_fruit_check, session['user_id']))
        db.commit()
        flash('تم حفظ الإعدادات', 'success')
        return redirect(url_for('farmer_settings'))
    user = db.execute("SELECT * FROM users WHERE id=?", (session['user_id'],)).fetchone()
    return render_template('farmer_settings.html', user=user)

@app.route('/farmer/dashboard')
@login_required
@farmer_required
def farmer_dashboard():
    db = get_db()
    uid = session['user_id']
    stats = {}
    stats['total_requests'] = db.execute("SELECT COUNT(*) c FROM harvest_requests WHERE farmer_id=?", (uid,)).fetchone()['c']
    stats['open_requests'] = db.execute("SELECT COUNT(*) c FROM harvest_requests WHERE farmer_id=? AND status='مفتوح'", (uid,)).fetchone()['c']
    stats['total_applicants'] = db.execute("""SELECT COUNT(*) c FROM applications a
        JOIN harvest_requests hr ON a.request_id=hr.id WHERE hr.farmer_id=?""", (uid,)).fetchone()['c']
    stats['total_scans'] = db.execute("SELECT COUNT(*) c FROM predictions WHERE farmer_id=?", (uid,)).fetchone()['c']
    yield_row = db.execute("SELECT COALESCE(SUM(estimated_yield),0) s, COALESCE(SUM(fruit_count),0) f FROM predictions WHERE farmer_id=?", (uid,)).fetchone()
    stats['total_yield'] = round(yield_row['s'], 2)
    stats['total_fruits'] = yield_row['f']
    last = db.execute("SELECT * FROM predictions WHERE farmer_id=? ORDER BY created_at DESC LIMIT 1", (uid,)).fetchone()
    stats['last_scan'] = last
    return render_template('farmer_dashboard.html', stats=stats)

@app.route('/farmer/requests')
@login_required
@farmer_required
def farmer_requests():
    db = get_db()
    requests = db.execute("""
        SELECT hr.*,
        (SELECT COUNT(*) FROM applications WHERE request_id=hr.id) as applicant_count
        FROM harvest_requests hr WHERE hr.farmer_id=? ORDER BY hr.created_at DESC
    """, (session['user_id'],)).fetchall()
    return render_template('farmer_requests.html', requests=requests, today=int(time.time()) , datetime=datetime)

@app.route('/farmer/new-request', methods=['GET','POST'])
@login_required
@farmer_required
def farmer_new_request():
    if request.method == 'POST':
        crop = request.form.get('crop','').strip()
        harvest_date = request.form.get('harvest_date','')
        volunteers_needed = request.form.get('volunteers_needed', 1, type=int)
        reward = request.form.get('reward','').strip()
        description = request.form.get('description','').strip()
        location = request.form.get('location','').strip()
        if not all([crop, harvest_date, location]):
            flash('يرجى ملء الحقول المطلوبة', 'error')
            return render_template('farmer_new_request.html')

        image_filename = ''
        if 'image' in request.files:
            file = request.files['image']
            if file and file.filename and allowed_file(file.filename):
                image_filename = str(uuid.uuid4()) + '.' + file.filename.rsplit('.', 1)[1].lower()
                file.save(os.path.join(app.config['UPLOAD_FOLDER'], image_filename))

        db = get_db()
        db.execute("""INSERT INTO harvest_requests
            (farmer_id,crop,harvest_date,location,volunteers_needed,reward,description,image)
            VALUES (?,?,?,?,?,?,?,?)""",
            (session['user_id'], crop, harvest_date, location, volunteers_needed, reward, description, image_filename))
        db.commit()
        flash('تم نشر طلب الحصاد بنجاح!', 'success')
        return redirect(url_for('farmer_requests'))
    return render_template('farmer_new_request.html')

@app.route('/farmer/request/<int:rid>')
@login_required
@farmer_required
def farmer_request_detail(rid):
    db = get_db()
    req = db.execute("SELECT * FROM harvest_requests WHERE id=? AND farmer_id=?",
                     (rid, session['user_id'])).fetchone()
    if not req:
        flash('الطلب غير موجود', 'error')
        return redirect(url_for('farmer_requests'))
    applicants = db.execute("""
        SELECT a.*, u.name, u.phone, u.skills
        FROM applications a JOIN users u ON a.volunteer_id=u.id
        WHERE a.request_id=?
    """, (rid,)).fetchall()

    rates = db.execute("""
        SELECT rated_id, AVG(score) score FROM ratings
        WHERE request_id=?
        GROUP BY rated_id
    """, (rid,)).fetchall()
    
    return render_template('farmer_request_detail.html', req=req, applicants=applicants, rates=rates)

@app.route('/farmer/application/<int:aid>/accept')
@login_required
@farmer_required
def accept_application(aid):
    db = get_db()
    app_row = db.execute("SELECT * FROM applications WHERE id=?", (aid,)).fetchone()
    if app_row:
        db.execute("UPDATE applications SET status='مقبول' WHERE id=?", (aid,))
        db.commit()
        flash('تم قبول المتطوع', 'success')
    return redirect(url_for('farmer_request_detail', rid=app_row['request_id']))

@app.route('/farmer/application/<int:aid>/reject')
@login_required
@farmer_required
def reject_application(aid):
    db = get_db()
    app_row = db.execute("SELECT * FROM applications WHERE id=?", (aid,)).fetchone()
    if app_row:
        db.execute("UPDATE applications SET status='مرفوض' WHERE id=?", (aid,))
        db.commit()
        flash('تم رفض الطلب', 'success')
    return redirect(url_for('farmer_request_detail', rid=app_row['request_id']))

@app.route('/farmer/edit-request/<int:rid>', methods=['GET','POST'])
@login_required
@farmer_required
def farmer_edit_request(rid):
    db = get_db()
    req = db.execute("SELECT * FROM harvest_requests WHERE id=? AND farmer_id=?",
                     (rid, session['user_id'])).fetchone()
    if not req:
        flash('الطلب غير موجود', 'error')
        return redirect(url_for('farmer_requests'))

    if request.method == 'POST':
        crop = request.form.get('crop','').strip()
        harvest_date = request.form.get('harvest_date','')
        volunteers_needed = request.form.get('volunteers_needed', 1, type=int)
        reward = request.form.get('reward','').strip()
        description = request.form.get('description','').strip()
        if not all([crop, harvest_date]):
            flash('يرجى ملء الحقول المطلوبة', 'error')
            return render_template('farmer_edit_request.html', req=req)

        image_filename = req['image'] if 'image' in req.keys() else ''
        if 'image' in request.files:
            file = request.files['image']
            if file and file.filename and allowed_file(file.filename):
                image_filename = str(uuid.uuid4()) + '.' + file.filename.rsplit('.', 1)[1].lower()
                file.save(os.path.join(app.config['UPLOAD_FOLDER'], image_filename))

        db.execute("""UPDATE harvest_requests
            SET crop=?, harvest_date=?, volunteers_needed=?, reward=?, description=?, image=?
            WHERE id=? AND farmer_id=?""",
            (crop, harvest_date, volunteers_needed, reward, description, image_filename, rid, session['user_id']))
        db.commit()
        flash('تم تحديث الطلب بنجاح!', 'success')
        return redirect(url_for('farmer_requests'))

    return render_template('farmer_edit_request.html', req=req)

# ─── AI Prediction Route ───
CROP_CONFIG = {
    'olives': {
        'name_ar': 'زيتون',
        'label': 'olive',
        'class_aliases': ['olive', 'olives'],
        'avg_weight': 0.005,       # ~5g per olive
        'worker_capacity': 2000,   # olives per worker per day
        'color': (80, 120, 0),     # olive-green boxes
    },
    'figs': {
        'name_ar': 'تين',
        'label': 'fig',
        'class_aliases': ['fig', 'figs'],
        'avg_weight': 0.050,       # ~50g per fig fruit
        'worker_capacity': 400,    # figs per worker per day
        'color': (130, 60, 140),   # purple boxes
    }
}

# ─── OpenAI Vision verification ───
OPENAI_VISION_MODEL = os.environ.get('OPENAI_VISION_MODEL', 'gpt-4o-mini')

def verify_crop_with_vision(image_path, crop_type):
    """Ask an OpenAI vision model to confirm the fruit type and estimate maturity.

    Returns a dict: {matches: bool, maturity_en: str, maturity_ar: str, note_ar: str, error: str|None}.
    """
    result = {
        'matches': True,
        'maturity_en': '',
        'maturity_ar': '',
        'note_ar': '',
        'error': None,
    }

    api_key = os.environ.get('OPENAI_API_KEY')
    if not api_key:
        result['error'] = 'OPENAI_API_KEY غير مضبوط في بيئة التشغيل'
        return result

    expected = CROP_CONFIG[crop_type]['label']  # 'olive' or 'fig'
    expected_ar = CROP_CONFIG[crop_type]['name_ar']

    try:
        from openai import OpenAI
        client = OpenAI(api_key=api_key)

        mime, _ = mimetypes.guess_type(image_path)
        if not mime:
            mime = 'image/jpeg'
        with open(image_path, 'rb') as f:
            b64 = base64.b64encode(f.read()).decode('ascii')
        data_url = f"data:{mime};base64,{b64}"

        system_prompt = (
            "You are an agronomy assistant. You will be given a photo and an expected fruit type. "
            "Decide if the photo's main subject is that fruit, and estimate the ripeness/maturity. "
            "Respond ONLY as minified JSON with keys: "
            "matches (boolean), maturity_en (one of: unripe, semi-ripe, ripe, overripe, unknown), "
            "maturity_ar (Arabic label: غير ناضج / نصف ناضج / ناضج / مفرط النضج / غير معروف), "
            "note_ar (short Arabic sentence, max 20 words, explaining what you see). "
            "If matches is false, still fill the other fields based on what you actually see."
        )
        user_text = f"Expected fruit: {expected} ({expected_ar}). Analyze the image."

        resp = client.chat.completions.create(
            model=OPENAI_VISION_MODEL,
            temperature=0,
            response_format={"type": "json_object"},
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": [
                    {"type": "text", "text": user_text},
                    {"type": "image_url", "image_url": {"url": data_url}},
                ]},
            ],
        )
        content = resp.choices[0].message.content or '{}'
        data = json.loads(content)
        result['matches'] = bool(data.get('matches', False))
        result['maturity_en'] = str(data.get('maturity_en', '') or '').strip()
        result['maturity_ar'] = str(data.get('maturity_ar', '') or '').strip()
        result['note_ar'] = str(data.get('note_ar', '') or '').strip()
    except Exception as e:
        result['error'] = str(e)
        print(f"⚠️ Vision verification failed: {e}")

    return result


def filter_boxes_by_crop(results, crop_type):
    """Return only the boxes whose predicted class matches the selected crop."""
    config = CROP_CONFIG[crop_type]
    aliases = {a.lower() for a in config['class_aliases']}
    names = results[0].names  # {idx: class_name}
    matched = []
    for box in results[0].boxes:
        cls_idx = int(box.cls[0])
        cls_name = str(names.get(cls_idx, '')).lower()
        if cls_name in aliases:
            matched.append(box)
    return matched

def render_custom_result(image_path, boxes, crop_type):
    """Render detection results with the user-selected crop label."""

    config = CROP_CONFIG[crop_type]
    label = config['label']
    color = config['color']

    img = cv2.imread(image_path)

    for box in boxes:
        x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
        conf = float(box.conf[0])
        # Draw bounding box
        cv2.rectangle(img, (x1, y1), (x2, y2), color, 2)
        # Draw label with user-selected crop type
        txt = f"{label} {conf:.2f}"
        font_scale = 0.4
        thickness = 1
        (tw, th), _ = cv2.getTextSize(txt, cv2.FONT_HERSHEY_SIMPLEX, font_scale, thickness)
        cv2.rectangle(img, (x1, y1 - th - 8), (x1 + tw + 4, y1), color, -1)
        cv2.putText(img, txt, (x1 + 2, y2 - 2), cv2.FONT_HERSHEY_SIMPLEX, font_scale, (255, 255, 255), thickness)
    
    return img


# -----------------------------
# SIMPLE RIPENESS CLASSIFIER
# -----------------------------
import numpy as np
def get_ripe_percentage(crops):
    """
    crops: list of images (numpy arrays from cv2)
    returns: ripe_percentage, ripe_count, total_count
    """

    ripe_count = 0
    total_count = 0

    for crop in crops:
        if crop is None:
            continue

        total_count += 1

        hsv = cv2.cvtColor(crop, cv2.COLOR_BGR2HSV)

        h_mean = np.mean(hsv[:, :, 0])
        s_mean = np.mean(hsv[:, :, 1])

        # simple ripeness rule (adjust later if needed)
        if h_mean < 30 and s_mean > 50:
            ripe_count += 1

    if total_count == 0:
        return 0, 0, 0

    ripe_percentage = (ripe_count / total_count) * 100

    return ripe_percentage, ripe_count, total_count

@app.route('/farmer/predict', methods=['GET','POST'])
@login_required
@farmer_required
def farmer_predict():
    db = get_db()
    uid = session['user_id']
    rows = db.execute("SELECT * FROM predictions WHERE farmer_id=? ORDER BY id DESC LIMIT 10", (uid,)).fetchall()
    return render_template('farmer_predict.html', rows=rows)

@login_required
@app.route('/image_predict', methods=['POST'])
def image_predict():
    if request.method == 'POST':
        if 'image' not in request.files:
            flash('يرجى رفع صورة', 'error')
            return render_template('farmer_predict.html')
        file = request.files['image']
        if file.filename == '' or not allowed_file(file.filename):
            flash('صيغة الملف غير مدعومة', 'error')
            return render_template('farmer_predict.html')

        crop_type = request.form.get('crop_type', 'olives')
        if crop_type not in CROP_CONFIG:
            crop_type = 'olives'
        config = CROP_CONFIG[crop_type]

        filename = str(uuid.uuid4()) + '.' + file.filename.rsplit('.', 1)[1].lower()
        filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        file.save(filepath)

        print(f" file uploaded: {filename}, crop type: {crop_type}")

        yolo = get_model()
        if yolo is None:
            flash('نموذج الذكاء الاصطناعي غير متاح حالياً', 'error')
            return render_template('farmer_predict.html')

        try:
            print(f"🔍 Starting prediction for {filename} as {crop_type}...")
            user = get_user()
            wrong_fruit_check_on = bool(user['wrong_fruit_check']) if user else True
            # Vision LLM check: verify crop type and estimate maturity
            vision = verify_crop_with_vision(filepath, crop_type)
            if vision['error']:
                flash(f"تعذر التحقق بالذكاء البصري: {vision['error']}", 'error')
                return f"تعذر التحقق بالذكاء البصري: {vision['error']}", 'error'
            if wrong_fruit_check_on and not vision['matches']:
                flash(
                    f"❌ ثمرة خاطئة — هذه الصورة لا تحتوي على {config['name_ar']}. "
                    + (f"({vision['note_ar']})" if vision['note_ar'] else ''),
                    'error')
                return f"❌ ثمرة خاطئة — هذه الصورة لا تحتوي على {config['name_ar']}. " + (f"({vision['note_ar']})" if vision['note_ar'] else ''), 'error'
            
            print(f"✅ Vision check passed: matches={vision['matches']}, maturity={vision['maturity_en']} ({vision['maturity_ar']}), note: {vision['note_ar']}")

            # Low confidence to detect as many fruits as possible
            print("🔍 Running YOLO detection...")
            print(f"Processing file: {filepath}")

            results = yolo(filepath, conf=0.10, iou=0.3)
            result_filename = 'result_' + filename
            result_path = os.path.join(app.config['RESULTS_FOLDER'], result_filename)
            
            # Only keep boxes whose class matches the selected crop (model has olive + fig classes)
            matched_boxes = filter_boxes_by_crop(results, crop_type)

            # -----------------------------
            # RIPENESS ANALYSIS USING SVM
            # -----------------------------
            img_original = cv2.imread(filepath)
            ripe_count = 0
            unripe_count = 0
            fruit_results = []
            for box in matched_boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())

                # crop detected fruit
                crop_img = img_original[y1:y2, x1:x2]
                if crop_img.size == 0:
                    continue

                # SVM prediction
                ripeness, confidence = predict_ripeness(crop_img)
                fruit_results.append({
                    "box": (x1, y1, x2, y2),
                    "ripeness": ripeness,
                    "confidence": confidence
                })

                if ripeness == "ripe":
                    ripe_count += 1
                else:
                    unripe_count += 1

            total_count = ripe_count + unripe_count

            # Ripeness percentage
            ripe_percentage = 0
            if total_count > 0:
                ripe_percentage = (ripe_count / total_count) * 100

            print(f"🍎 Ripe: {ripe_count}")
            print(f"🍏 Unripe: {unripe_count}")
            print(f"📊 Ripe Percentage: {ripe_percentage:.2f}%")

            # Custom rendering with the correct crop label
            result_img = render_custom_result(filepath, matched_boxes, crop_type)
            accuries = []
            #result_img = cv2.imread(filepath)
            for item in fruit_results:
                x1, y1, x2, y2 = item["box"]
                ripeness = item["ripeness"]
                confidence = item["confidence"]
                print("Ripness Accuracy: ", confidence)
                accuries.append(confidence)

                # colors
                if ripeness == "ripe":
                    color = (0, 255, 0)
                    label = f"Ripe {confidence:.1f}%"
                else:
                    color = (0, 0, 255)
                    label = f"Unripe {confidence:.1f}%"

                # draw
                cv2.rectangle(result_img, (x1, y1), (x2, y2), color, 1)
                cv2.putText(result_img, label, (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 1)
            
            maxripeness = max(accuries)

            cv2.imwrite(result_path, result_img)

            fruit_count = len(matched_boxes)
            estimated_yield = 0
            if ripe_percentage > 0:
                estimated_yield = round(ripe_percentage/100, 2) #round(fruit_count * config['avg_weight'], 2)
            if estimated_yield > 2:
                volunteers_recommended = 4
            elif estimated_yield > 1:
                volunteers_recommended = 3
            elif estimated_yield >= 0.5:
                volunteers_recommended = 2
            else:
                volunteers_recommended = 1

            db = get_db()
            db.execute("""INSERT INTO predictions
                (farmer_id, image_path, result_path, fruit_count, estimated_yield, volunteers_recommended)
                VALUES (?,?,?,?,?,?)""",
                (session['user_id'], filename, result_filename, fruit_count, estimated_yield, volunteers_recommended))
            db.commit()

            return render_template('farmer_predict_result.html',
                fruit_count=fruit_count,
                estimated_yield=estimated_yield,
                volunteers_recommended=volunteers_recommended,
                original_image=filename,
                result_image=result_filename,
                crop_name=config['name_ar'],                
                ripe_count=ripe_count,
                unripe_count=unripe_count,
                ripe_percentage=round(ripe_percentage, 2),
                maxripeness = maxripeness,
                detection_accuracy = '94%',
                vision_note=vision['note_ar'],
                maturity_ar=vision['maturity_ar'],
                maturity_en=vision['maturity_en'],
            )
            '''
                maturity_ar=vision['maturity_ar'],
                maturity_en=vision['maturity_en'],
                vision_note=vision['note_ar'],
                '''
        
        except Exception as e:
            print(f"❌ Prediction error: {e}")
            flash(f'حدث خطأ أثناء التحليل: {str(e)}', 'error')
            return f"❌ Prediction error: {e}"

# ====================
# ─── Video upload ───
# ====================
Vide_ALLOWED_EXTENSIONS = {'mp4', 'avi'}
stop_detection = False  

# تهيئة كائن الكاميرا كـ None لتفادي أخطاء الـ NameError والكراش
cap = None 

def vide_allowed_file(filename):
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in Vide_ALLOWED_EXTENSIONS

@app.route('/uploadVideo', methods=['POST'])
def upload_video():
    if 'video' not in request.files:
        return jsonify({'error': 'No file part'}), 400

    file = request.files['video']
    if file.filename == '':
        return jsonify({'error': 'لم يتم اختيار ملف'}), 400

    if file and vide_allowed_file(file.filename):
        filename = secure_filename(file.filename)
        filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        file.save(filepath)

        return jsonify({
            'message': 'تم رفع الفيديو بنجاح',
            'filename': filename
        })
    return jsonify({'error': 'ملف خاطئ'}), 400

fruit_count = 0
max_fruit_count = 0  
count_lock = Lock()

#-----------------------------------
# دالات جلب العدادات مع هيدرز تمنع كاش المتصفح نهائياً
#-----------------------------------
@app.route("/fruit_count")
def get_fruit_count():
    global fruit_count
    with count_lock:
        count = fruit_count
    response = jsonify({"count": count})
    response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate"
    response.headers["Pragma"] = "no-cache"
    response.headers["Expires"] = "0"
    return response

@app.route("/max_fruit_count")
def get_max_fruit_count():
    global max_fruit_count
    with count_lock:
        max_count = max_fruit_count
    response = jsonify({"max_count": max_count})
    response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate"
    response.headers["Pragma"] = "no-cache"
    response.headers["Expires"] = "0"
    return response

# -----------------------------
# دالة توليد إطارات الفيديو (YOLO Loop)
from collections import deque

# -----------------------------
# -----------------------------
# دالة توليد إطارات الفيديو مع تفعيل بنك الداتا وحفظ صور النتائج تلقائياً في السيرفر
def generate_frames(video_path):
    global fruit_count, max_fruit_count, stop_detection, cap  
    
    stop_detection = False 
    with count_lock:
        fruit_count = 0
        max_fruit_count = 0

    # الذاكرة الرقمية لتثبيت الألوان بناءً على الـ ID الخاص بكل ثمرة
    fruit_history = {}
    
    # حجز متغير الفريم الأخير في مستوى أعلى لضمان بقائه في الذاكرة
    last_processed_frame = None

    cap = cv2.VideoCapture(video_path)  
    
    try:
        while True:
            if stop_detection:  
                break
                
            success, frame = cap.read()
            if not success:
                break

            # تشغيل تتبع YOLO الذكي لربط داتا الأجسام
            results = model.track(frame, conf=0.25, iou=0.35, persist=True)
            count = 0
            
            img_original = frame.copy()
            model_names = results[0].names 

            for r in results:
                if r.boxes is None:
                    continue

                for box in r.boxes:
                    cls_idx = int(box.cls[0])
                    cls_name = str(model_names.get(cls_idx, '')).lower()
                    
                    if any(x in cls_name for x in ['olive', 'olives', 'fig', 'figs', 'fruit']):
                        x1, y1, x2, y2 = map(int, box.xyxy[0])
                        
                        crop_img = img_original[y1:y2, x1:x2]
                        if crop_img.size == 0:
                            continue

                        # قراءة داتا النضوج من نموذج SVM الخاص بك
                        ripeness, confidence = predict_ripeness(crop_img)
                        count += 1

                        # تنسيق أرقام النسبة المئوية
                        if confidence < 1.0:
                            confidence = confidence * 100
                        elif confidence > 100.0:
                            confidence = 100.0

                        track_id = int(box.id[0]) if box.id is not None else None

                        # منطق استقرار تصنيف الألوان عبر تصويت الأغلبية
                        if track_id is not None:
                            if track_id not in fruit_history:
                                fruit_history[track_id] = deque(maxlen=7)
                            
                            fruit_history[track_id].append(ripeness)
                            final_ripeness = max(set(fruit_history[track_id]), key=fruit_history[track_id].count)
                        else:
                            final_ripeness = ripeness

                        # الألوان والنصوص الطافية النظيفة (أخضر=ناضج، أحمر=غير ناضج) بدون أي خلفية مصمتة
                        if final_ripeness == "ripe":
                            color = (0, 255, 0)            # أخضر صريح (طابق دالة الصور)
                            label = f"Ripe {confidence:.1f}%"
                        else:
                            color = (0, 0, 255)            # أحمر صريح (طابق دالة الصور)
                            label = f"Unripe {confidence:.1f}%"

                        # 1. رسم المربع النحيف حول الثمرة (سمك=1)
                        cv2.rectangle(frame, (x1, y1), (x2, y2), color, 1)
                        
                        # 2. كتابة النص النقي بدون خلفية مصمتة أعلى الصندوق مباشرة (مقاس خط=0.6)
                        cv2.putText(frame, label, (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 1)

            with count_lock:
                fruit_count = count
                if fruit_count > max_fruit_count:
                    max_fruit_count = fruit_count  

            # تصميم لوحة العدادات العلوية الشفافة لمنصة نمو
            overlay = frame.copy()
            ui_color = (30, 70, 40) 
            cv2.rectangle(overlay, (15, 15), (250, 90), ui_color, -1)
            cv2.addWeighted(overlay, 0.75, frame, 0.25, 0, frame)
            
            cv2.putText(frame, "AI VISION SYSTEM", (25, 34), cv2.FONT_HERSHEY_SIMPLEX, 0.45, (200, 255, 200), 1, cv2.LINE_AA)
            cv2.putText(frame, f"Current Count: {fruit_count}", (25, 58), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 255, 255), 2, cv2.LINE_AA)
            cv2.putText(frame, f"Max Tracked: {max_fruit_count}", (25, 80), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (255, 255, 255), 2, cv2.LINE_AA)

            # الاحتفاظ بنسخة مستقلة وآمنة من الإطار الحالي قبل الخروج لتوليد الصورة النهائية
            last_processed_frame = frame.copy()

            _, buffer = cv2.imencode('.jpg', frame)
            frame_bytes = buffer.tobytes()
            
            time.sleep(0.04) 
            
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')

    except Exception as e:
        print(f"❌ Error during video frame generation: {e}")

    finally:
        # 💡 السر الحاسم للحفظ: عند الضغط على زر الحفظ، يتم تحرير الكاميرا أولاً
        # ثم نقوم بعملية الحفظ المباشر هنا داخل البلوك الآمن لضمان الكتابة في المجلد قبل إغلاق النظام
        if last_processed_frame is not None:
            video_name = os.path.basename(video_path)
            result_img_name = "result_v_" + video_name.rsplit('.', 1)[0] + ".jpg"
            save_path = os.path.join(app.config['RESULTS_FOLDER'], result_img_name)
            cv2.imwrite(save_path, last_processed_frame)
            print(f"📸 Automatically captured and stored final frame: {save_path}")

        if cap is not None:
            cap.release()
            cap = None
        cv2.destroyAllWindows()




@app.route('/video_feed')
def video_feed():
    video_name = request.args.get('video')
    if not video_name:
        return "No video selected", 400

    path = os.path.join(app.config['UPLOAD_FOLDER'], video_name)
    if not os.path.exists(path):
        return "Video not found", 404

    return Response(generate_frames(path),
                    mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route("/loadvideo/<string:video_path>")
def loadvideo(video_path):
    return render_template("loadvideo.html", video_path=video_path)  

@app.route('/stop_detection', methods=['POST'])
def stop_video_detection():
    global stop_detection, cap, fruit_count, max_fruit_count

    stop_detection = True

    if cap is not None:
        cap.release()
        cap = None

    fruit_count = 0
    max_fruit_count = 0

    return jsonify({
        "message": "تم إيقاف التوقع وتصفير العدادات بنجاح"
    })

@app.route('/save_video_prediction', methods=['POST'])
def save_video_prediction():
    fruitcount = int(request.form['fruit_count'])
    video_name = request.form['video_name']
    farmer_id = session['user_id']

    recommended_volunteers = 0
    if fruitcount > 20:
        recommended_volunteers = 4
    elif fruitcount > 10:
        recommended_volunteers = 3
    elif fruitcount > 5:
        recommended_volunteers = 2

    # اسم صورة النتيجة المرتبطة بالفيديو والتي تم حفظها في السيرفر تلقائياً
    result_img_name = "result_v_" + video_name.rsplit('.', 1)[0] + ".jpg"

    db = get_db()
    # 💡 قمنا بإضافة اسم الصورة 'result_v_...' إلى قاعدة البيانات بدلاً من اسم الفيديو الخام فقط لتعمل كصورة تقرير
    db.execute("""INSERT INTO predictions (farmer_id, fruit_count, image_path, result_path, volunteers_recommended, created_at) 
               VALUES (?, ?, ?, ?, ?, ?)""", 
               (farmer_id, fruitcount, video_name, result_img_name, recommended_volunteers, datetime.utcnow()))
    db.commit()
    
    stop_video_detection()  

    return "تم حفظ التوقع وتصفير النظام وحفظ لقطة النتائج بنجاح", 200

@app.route('/delpredict/<string:id>', methods=['POST'])
def delpredict(id):
    db = get_db()
    uid = session['user_id']
    db.execute("DELETE FROM predictions WHERE id=? AND farmer_id=?", (id, uid))
    db.commit()
    return "تم حذف السجل", 200

# --------------- Rating -------------
@app.route('/save_rating', methods=['POST'])
def save_rating():
    request_id = request.form['request_id']
    score = request.form['score']
    rater_id = session['user_id']
    comment = request.form['comment']
    rated_id = request.form['rated_id']

    db = get_db()
    db.execute(""" INSERT INTO ratings (rater_id, rated_id, request_id, score, comment, created_at) 
               VALUES (?, ?, ?, ?, ?, ?)""", (rater_id, rated_id, request_id, score, comment, datetime.utcnow()))
    db.commit()
    return "تم التقييم", 200


# ─── Volunteer Routes ───
@app.route('/volunteer/dashboard')
@login_required
@volunteer_required
def volunteer_dashboard():
    return render_template('volunteer_dashboard.html')

@app.route('/volunteer/search')
@login_required
@volunteer_required
def volunteer_search():
    db = get_db()
    q = request.args.get('q', '')
    crop_filter = request.args.get('crop', '')
    date_filter = request.args.get('date', '')

    query = """
        SELECT hr.*, u.name as farmer_name, u.location as farmer_location,
        (SELECT COUNT(*) FROM applications WHERE request_id=hr.id) as applicant_count
        FROM harvest_requests hr JOIN users u ON hr.farmer_id=u.id
        WHERE hr.status='مفتوح'
    """
    params = []
    if q:
        query += " AND (hr.crop LIKE ? OR hr.location LIKE ? OR u.name LIKE ?)"
        params.extend([f'%{q}%', f'%{q}%', f'%{q}%'])
    if crop_filter:
        query += " AND hr.crop=?"
        params.append(crop_filter)
    if date_filter:
        query += " AND hr.harvest_date=?"
        params.append(date_filter)
    query += " ORDER BY hr.created_at DESC"
    requests = db.execute(query, params).fetchall()

    crops = db.execute("SELECT DISTINCT crop FROM harvest_requests WHERE status='مفتوح'").fetchall()
    return render_template('volunteer_search.html', requests=requests, crops=crops,
                           q=q, crop_filter=crop_filter, date_filter=date_filter)

@app.route('/volunteer/opportunity/<int:rid>')
@login_required
@volunteer_required
def volunteer_opportunity_detail(rid):
    db = get_db()
    req = db.execute("""
        SELECT hr.*, u.name as farmer_name, u.phone as farmer_phone,
        u.location as farmer_location
        FROM harvest_requests hr JOIN users u ON hr.farmer_id=u.id
        WHERE hr.id=?
    """, (rid,)).fetchone()
    if not req:
        flash('الفرصة غير موجودة', 'error')
        return redirect(url_for('volunteer_search'))

    already_applied = db.execute(
        "SELECT * FROM applications WHERE request_id=? AND volunteer_id=?",
        (rid, session['user_id'])).fetchone()

    ratings = db.execute("""
        SELECT AVG(score) as avg_score, COUNT(*) as count
        FROM ratings WHERE rated_id=?
    """, (req['farmer_id'],)).fetchone()

    related = db.execute("""
        SELECT hr.*, u.name as farmer_name
        FROM harvest_requests hr JOIN users u ON hr.farmer_id=u.id
        WHERE hr.id!=? AND hr.status='مفتوح' LIMIT 3
    """, (rid,)).fetchall()

    return render_template('volunteer_opportunity_detail.html',
        req=req, already_applied=already_applied, ratings=ratings, related=related)

@app.route('/volunteer/apply/<int:rid>')
@login_required
@volunteer_required
def volunteer_apply(rid):
    db = get_db()
    existing = db.execute("SELECT * FROM applications WHERE request_id=? AND volunteer_id=?",
                          (rid, session['user_id'])).fetchone()
    if existing:
        flash('لقد تقدمت بالفعل لهذه الفرصة', 'info')
    else:
        db.execute("INSERT INTO applications (request_id, volunteer_id) VALUES (?,?)",
                   (rid, session['user_id']))
        db.commit()
        flash('تم إرسال طلبك بنجاح! سيتم إعلامك برد المزارع.', 'success')
    return redirect(url_for('volunteer_opportunity_detail', rid=rid))

@app.route('/volunteer/my-requests')
@login_required
@volunteer_required
def volunteer_my_requests():
    db = get_db()
    apps = db.execute("""
        SELECT a.*, hr.crop, hr.harvest_date, hr.location, hr.farmer_id,
        u.name as farmer_name, u.phone as farmer_phone
        FROM applications a
        JOIN harvest_requests hr ON a.request_id=hr.id
        JOIN users u ON hr.farmer_id=u.id
        WHERE a.volunteer_id=? ORDER BY a.applied_at DESC
    """, (session['user_id'],)).fetchall()
    return render_template('volunteer_my_requests.html', apps=apps)

# ─── Messaging Routes ───
@app.route('/chat/<int:other_id>/<int:request_id>', methods=['GET','POST'])
@login_required
def chat(other_id, request_id):
    db = get_db()
    if request.method == 'POST':
        content = request.form.get('content','').strip()
        if content:
            db.execute("INSERT INTO messages (sender_id, receiver_id, request_id, content) VALUES (?,?,?,?)",
                       (session['user_id'], other_id, request_id, content))
            db.commit()
    messages = db.execute("""
        SELECT m.*, u.name as sender_name
        FROM messages m JOIN users u ON m.sender_id=u.id
        WHERE m.request_id=? AND
        ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
        ORDER BY m.sent_at ASC
    """, (request_id, session['user_id'], other_id, other_id, session['user_id'])).fetchall()

    other_user = db.execute("SELECT * FROM users WHERE id=?", (other_id,)).fetchone()
    req = db.execute("SELECT * FROM harvest_requests WHERE id=?", (request_id,)).fetchone()
    return render_template('chat.html', messages=messages, other_user=other_user, req=req)

# ─── Rating Route ───
@app.route('/rate/<int:user_id>/<int:request_id>', methods=['POST'])
@login_required
def rate_user(user_id, request_id):
    score = request.form.get('score', 0, type=int)
    comment = request.form.get('comment', '')
    if 1 <= score <= 5:
        db = get_db()
        existing = db.execute("SELECT * FROM ratings WHERE rater_id=? AND rated_id=? AND request_id=?",
                              (session['user_id'], user_id, request_id)).fetchone()
        if not existing:
            db.execute("INSERT INTO ratings (rater_id, rated_id, request_id, score, comment) VALUES (?,?,?,?,?)",
                       (session['user_id'], user_id, request_id, score, comment))
            db.commit()
            flash('تم إرسال التقييم بنجاح', 'success')
    return redirect(request.referrer or url_for('home'))


if __name__ == '__main__':
    init_db()
    print("🌱 Nomu Platform Starting...")
    print("📍 http://127.0.0.1:5000")
    print("⏳ Loading AI model...")
    load_model()
    print("✅ Ready!")
    app.run(debug=True, port=5000, use_reloader=False)
save_video_prediction