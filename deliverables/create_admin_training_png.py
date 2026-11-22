from PIL import Image, ImageDraw, ImageFont
from pathlib import Path

OUT = Path('/Users/user/Sites/mywap/deliverables/admin-negeri-png')
OUT.mkdir(parents=True, exist_ok=True)
W, H = 1920, 1080
BG = '#F7F8F3'
NAVY = '#123B4A'
GREEN = '#0F5C45'
DARKGREEN = '#084634'
MINT = '#DDEFE5'
GOLD = '#D8A43B'
INK = '#203238'
MUTED = '#65767A'
WHITE = '#FFFFFF'
RED = '#B84A4A'
LINE = '#DCE5DF'

FONT_DIR = '/System/Library/Fonts/Supplemental'
def font(name, size):
    candidates = [
        f'{FONT_DIR}/{name}.ttf',
        f'/System/Library/Fonts/{name}.ttf',
        '/Library/Fonts/Arial.ttf',
    ]
    for p in candidates:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()

REG = font('Arial', 28)
SMALL = font('Arial', 23)
BODY = font('Arial', 30)
BODY_B = font('Arial Bold', 30)
TITLE = font('Arial Bold', 58)
SUB = font('Arial', 29)
BIG = font('Arial Bold', 78)
NUM = font('Arial Bold', 34)

# The repository asset is currently a placeholder, so use a neutral branded badge
# rather than inventing an official WADAH logo.
logo = Image.new('RGBA', (220, 220), (0, 0, 0, 0))
ld = ImageDraw.Draw(logo)
ld.ellipse((8, 8, 212, 212), fill=GREEN)
ld.ellipse((28, 28, 192, 192), outline=GOLD, width=8)
ld.text((110, 82), 'myWAP', font=font('Arial Bold', 30), fill=WHITE, anchor='mm')
ld.text((110, 132), 'WADAH', font=font('Arial Bold', 23), fill='#CFE5DA', anchor='mm')

def rr(d, box, radius=28, fill=WHITE, outline=None, width=2):
    d.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)

def text(d, xy, s, f=BODY, fill=INK, anchor=None, spacing=8):
    d.multiline_text(xy, s, font=f, fill=fill, anchor=anchor, spacing=spacing)

def header(im, title_text, eyebrow=None, slide_no=None):
    d = ImageDraw.Draw(im)
    d.rectangle((0,0,W,14), fill=GREEN)
    im.paste(logo.resize((150,150), Image.Resampling.LANCZOS), (78,52), logo.resize((150,150), Image.Resampling.LANCZOS))
    text(d, (270, 72), 'myWAP', font('Arial Bold', 28), NAVY, spacing=0)
    text(d, (270, 108), 'WADAH Malaysia', font('Arial', 22), MUTED, spacing=0)
    if eyebrow:
        text(d, (78, 222), eyebrow.upper(), font('Arial Bold', 20), GOLD, spacing=0)
        y = 260
    else:
        y = 220
    text(d, (78, y), title_text, TITLE, NAVY, spacing=4)
    if slide_no:
        text(d, (1810, 68), f'{slide_no:02d}', font('Arial Bold', 24), MUTED, anchor='ra', spacing=0)
    return d

def footer(d, s='Latihan Admin Cawangan / Negeri WADAH'):
    d.line((78, 1010, 1842, 1010), fill=LINE, width=2)
    text(d, (78, 1035), s, font('Arial', 20), MUTED, spacing=0)
    text(d, (1842, 1035), 'myWAP', font('Arial Bold', 20), GREEN, anchor='ra', spacing=0)

def save(im, n):
    im.save(OUT / f'{n}.png', 'PNG', optimize=True)

# Slide 1
im = Image.new('RGB', (W,H), BG); d = header(im, 'myWAP untuk\nAdmin Cawangan & Negeri WADAH', 'Pengenalan ringkas', 1)
# hero panel
rr(d, (78, 470, 1130, 895), 34, fill=NAVY)
text(d, (135, 535), 'Satu sistem untuk\nurus ahli dan aktiviti\ncawangan.', font('Arial Bold', 54), WHITE, spacing=10)
text(d, (135, 765), 'Semak  •  Urus  •  Rekod  •  Hebah', font('Arial', 27), '#CFE5DA', spacing=0)
# right feature cards
features = [('AHLI', 'Semak rekod ahli'), ('PROGRAM', 'Urus acara & peserta'), ('KEHADIRAN', 'Scan QR ahli'), ('HEBAHAN', 'Maklumkan ahli')]
for i,(a,b) in enumerate(features):
    y = 470 + i*105
    rr(d, (1220,y,1815,y+78), 22, fill=WHITE, outline=LINE, width=2)
    d.ellipse((1250,y+19,1295,y+64), fill=GREEN if i != 3 else GOLD)
    text(d, (1325,y+17), a, font('Arial Bold', 20), NAVY, spacing=0)
    text(d, (1325,y+45), b, font('Arial', 21), MUTED, spacing=0)
footer(d)
save(im, '01-pengenalan')

# Slide 2
im = Image.new('RGB', (W,H), BG); d = header(im, 'Akaun admin & bantuan kepada ahli', 'Akses dan sokongan operasi', 2)
# left role cards
rr(d, (78, 390, 895, 890), 30, fill=WHITE, outline=LINE, width=2)
text(d, (125, 435), 'Dua akaun, dua tujuan', font('Arial Bold', 33), NAVY, spacing=0)
rr(d, (125, 510, 820, 660), 22, fill=MINT)
text(d, (160, 545), 'AKAUN ADMIN NEGERI', font('Arial Bold', 22), GREEN, spacing=0)
text(d, (160, 585), 'Untuk urus tugas admin\ndan data cawangan / negeri.', BODY, INK, spacing=5)
rr(d, (125, 700, 820, 850), 22, fill='#FFF5DF')
text(d, (160, 735), 'AKAUN INDIVIDU SETIAUSAHA', font('Arial Bold', 22), '#9A6A11', spacing=0)
text(d, (160, 775), 'Setiausaha tetap ada akaun\nperibadi sendiri untuk urusan ahli.', BODY, INK, spacing=5)
# right support flow
rr(d, (980, 390, 1842, 890), 30, fill=NAVY)
text(d, (1030, 435), 'Jika ahli ada masalah login', font('Arial Bold', 33), WHITE, spacing=0)
steps = [('1', 'Aktifkan akaun tanpa OTP', 'Untuk ahli sedia ada yang belum pernah login.'), ('2', 'Tukar password secara manual', 'Bantu ahli yang kurang celik IT atau OTP bermasalah.'), ('3', 'Ahli login dan tukar sendiri', 'Password sementara perlu ditukar selepas berjaya masuk.')]
for i,(n,h,b) in enumerate(steps):
    y = 530 + i*105
    d.ellipse((1035,y,1095,y+60), fill=GOLD)
    text(d, (1065,y+30), n, NUM, NAVY, anchor='mm', spacing=0)
    text(d, (1130,y), h, font('Arial Bold', 25), WHITE, spacing=0)
    text(d, (1130,y+36), b, font('Arial', 21), '#CFE5DA', spacing=0)
text(d, (1035, 835), 'Akses admin negeri disediakan oleh Admin Organisasi / Superadmin.\nJangan kongsi akaun atau password sesama petugas.', font('Arial Bold', 21), '#FFE7A7', spacing=5)
footer(d)
save(im, '02-akaun-dan-bantuan')

# Slide 3
im = Image.new('RGB', (W,H), BG); d = header(im, 'Pendaftaran, ahli & pertukaran cawangan', 'Siapa buat apa?', 3)
# flow top
text(d, (90, 350), 'FLOW PENDAFTARAN AHLI', font('Arial Bold', 22), GREEN, spacing=0)
flow = [('1','Admin kongsi\nlink pendaftaran'), ('2','User daftar &\npilih negeri'), ('3','Sistem masukkan\nke cawangan'), ('4','Admin semak\nrekod ahli')]
for i,(n,label) in enumerate(flow):
    x = 90 + i*440
    rr(d, (x, 405, x+330, 555), 24, fill=WHITE, outline=LINE, width=2)
    d.ellipse((x+28,435,x+88,495), fill=GREEN)
    text(d, (x+58,465), n, NUM, WHITE, anchor='mm', spacing=0)
    text(d, (x+112, 438), label, font('Arial Bold', 25), NAVY, spacing=5)
    if i < 3:
        d.line((x+335,480,x+415,480), fill=GOLD, width=7)
        d.polygon([(x+415,480),(x+395,468),(x+395,492)], fill=GOLD)
# bottom three lanes
rr(d, (90, 630, 650, 900), 26, fill=MINT)
text(d, (130, 675), 'ADMIN CAWANGAN / NEGERI', font('Arial Bold', 21), GREEN, spacing=0)
text(d, (130, 720), '• Cari & semak ahli\n• Lihat pergerakan masuk / keluar\n• Semak status yuran sahaja\n• Pantau rekod cawangan', BODY, INK, spacing=9)
rr(d, (700, 630, 1230, 900), 26, fill='#FFF5DF')
text(d, (740, 675), 'ADMIN ORGANISASI WADAH', font('Arial Bold', 21), '#9A6A11', spacing=0)
text(d, (740, 720), '• Urus yuran\n• Approve / reject pertukaran\n• Kemas kini keputusan rasmi\n• Urus tindakan organisasi', BODY, INK, spacing=9)
rr(d, (1280, 630, 1830, 955), 26, fill=NAVY)
text(d, (1320, 675), 'NOTIFIKASI', font('Arial Bold', 21), GOLD, spacing=0)
text(d, (1320, 710), 'Admin Cawangan\npantau sahaja.\n\nAdmin Organisasi\napprove / reject.\n\nAhli menerima\nnotifikasi.', font('Arial', 25), WHITE, spacing=5)
footer(d)
save(im, '03-ahli-dan-pertukaran')

# Slide 4
im = Image.new('RGB', (W,H), BG); d = header(im, 'Program, kehadiran & hebahan', 'Operasi semasa program', 4)
# left event
rr(d, (78, 350, 930, 900), 30, fill=WHITE, outline=LINE, width=2)
text(d, (125, 395), 'PROGRAM & ACARA', font('Arial Bold', 24), GREEN, spacing=0)
text(d, (125, 450), 'Admin boleh tetapkan:', font('Arial Bold', 28), NAVY, spacing=0)
text(d, (125, 510), 'Tajuk • Kategori • Tarikh & masa\nLokasi / Google Maps\nFizikal atau dalam talian\nSiapa yang boleh mendaftar\nPoster program', BODY, INK, spacing=10)
# attendance mini flow
text(d, (125, 700), 'KEHADIRAN', font('Arial Bold', 24), GOLD, spacing=0)
att = [('1','Pilih program'),('2','Scan QR ahli'),('3','Semak berjaya'),('4','Manual no. ahli jika QR gagal')]
for i,(n,label) in enumerate(att):
    x = 125 + i*185
    d.ellipse((x,760,x+50,810), fill=GOLD)
    text(d, (x+25,785), n, font('Arial Bold', 22), NAVY, anchor='mm', spacing=0)
    text(d, (x-5,830), label, font('Arial', 18), MUTED, spacing=0)
# right
rr(d, (1010, 350, 1842, 900), 30, fill=NAVY)
text(d, (1060, 395), 'HEBAHAN & MOBILE APP', font('Arial Bold', 24), GOLD, spacing=0)
text(d, (1060, 460), 'Hebahan Cawangan', font('Arial Bold', 32), WHITE, spacing=0)
text(d, (1060, 520), '• Hantar notifikasi in-app\n• Pilih ahli cawangan sebagai sasaran\n• Pilih pautan program / luar\n• Hantar terus atau jadualkan', BODY, '#CFE5DA', spacing=10)
# mobile card
rr(d, (1060, 700, 1785, 850), 22, fill='#1B5968', outline='#2C7180', width=2)
text(d, (1100, 730), 'Admin juga boleh guna mobile app', font('Arial Bold', 25), WHITE, spacing=0)
text(d, (1100, 775), 'Semak dashboard • ahli • yuran •\nkehadiran • hebahan ketika bergerak', font('Arial', 22), '#CFE5DA', spacing=5)
footer(d)
save(im, '04-program-kehadiran-hebah')

print('\n'.join(str(OUT / f'{n}.png') for n in ['01-pengenalan','02-akaun-dan-bantuan','03-ahli-dan-pertukaran','04-program-kehadiran-hebah']))
