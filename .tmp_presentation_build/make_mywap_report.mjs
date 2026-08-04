import fs from "node:fs/promises";
import { Presentation, PresentationFile } from "@oai/artifact-tool";

const FINAL_PPTX = "/Users/user/Sites/mywap/deliverables/mywap-laporan-kemajuan-ogos-2026.pptx";
const OUT_DIR = "/Users/user/Sites/mywap/.tmp_presentation_build/rendered";

const colors = {
  ink: "#123047",
  text: "#244257",
  muted: "#6B7F8F",
  line: "#D7E1E8",
  pale: "#F5F0E8",
  white: "#FFFFFF",
  gold: "#C88B3A",
  teal: "#1E7A78",
  green: "#70A37F",
  coral: "#D77A61",
  softBlue: "#DDEAF2",
  softGold: "#F6E8D4",
  softTeal: "#D9F0ED",
  softRose: "#F6E3DD",
};

const slideSize = { width: 1280, height: 720 };
const page = { left: 64, top: 48, width: 1152, height: 624 };

async function writeBlob(path, blob) {
  await fs.writeFile(path, new Uint8Array(await blob.arrayBuffer()));
}

function addBox(slide, opts) {
  return slide.shapes.add({
    geometry: opts.geometry || "roundRect",
    position: opts.position,
    fill: opts.fill ?? colors.white,
    line: opts.line ?? { style: "solid", fill: "none", width: 0 },
    borderRadius: opts.borderRadius,
    shadow: opts.shadow,
    name: opts.name,
  });
}

function addText(slide, text, position, style = {}) {
  const shape = slide.shapes.add({
    geometry: "textbox",
    position,
    fill: "none",
    line: { style: "solid", fill: "none", width: 0 },
  });
  shape.text = text;
  shape.text.style = {
    fontFace: style.fontFace || "Arial",
    fontSize: style.fontSize || 20,
    color: style.color || colors.text,
    bold: style.bold || false,
    italic: style.italic || false,
    align: style.align || "left",
    valign: style.valign || "top",
    breakLine: style.breakLine ?? true,
  };
  return shape;
}

function addRule(slide, left, top, width, fill = colors.line, height = 2) {
  return addBox(slide, {
    geometry: "rect",
    position: { left, top, width, height },
    fill,
    line: { style: "solid", fill: "none", width: 0 },
  });
}

function addBulletList(slide, items, left, top, width, fontSize = 18, color = colors.text, gap = 28) {
  items.forEach((item, index) => {
    addText(slide, `• ${item}`, { left, top: top + index * gap, width, height: gap }, { fontSize, color });
  });
}

function money(n) {
  return new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function addHeaderBand(slide, title, subtitle) {
  addText(slide, title, { left: page.left, top: 44, width: 760, height: 44 }, { fontSize: 34, bold: true, color: colors.ink });
  addText(slide, subtitle, { left: page.left, top: 88, width: 780, height: 32 }, { fontSize: 16, color: colors.muted });
  addRule(slide, page.left, 126, page.width, colors.line, 2);
}

function slide1(presentation) {
  const slide = presentation.slides.add();
  slide.background.fill = colors.pale;

  addBox(slide, {
    geometry: "rect",
    position: { left: 0, top: 0, width: 1280, height: 720 },
    fill: colors.pale,
    line: { style: "solid", fill: "none", width: 0 },
  });
  addBox(slide, {
    geometry: "rect",
    position: { left: 920, top: 0, width: 360, height: 720 },
    fill: colors.ink,
    line: { style: "solid", fill: "none", width: 0 },
  });
  addBox(slide, {
    geometry: "rect",
    position: { left: 882, top: 0, width: 38, height: 720 },
    fill: colors.gold,
    line: { style: "solid", fill: "none", width: 0 },
  });

  addText(slide, "Laporan Kemajuan Projek", { left: 72, top: 86, width: 520, height: 58 }, { fontSize: 22, bold: true, color: colors.gold });
  addText(slide, "myWAP", { left: 72, top: 150, width: 480, height: 80 }, { fontSize: 56, bold: true, color: colors.ink });
  addText(slide, "Timeline pembangunan, status semasa, dan pelan penyelesaian bayaran sebelum muktamar September 2026.", { left: 72, top: 238, width: 690, height: 100 }, { fontSize: 24, color: colors.text });

  addBox(slide, {
    position: { left: 72, top: 382, width: 770, height: 180 },
    fill: colors.white,
    line: { style: "solid", fill: colors.line, width: 1 },
    borderRadius: "rounded-2xl",
  });
  addText(slide, "Status Ringkas", { left: 102, top: 410, width: 220, height: 30 }, { fontSize: 20, bold: true, color: colors.ink });
  addBulletList(slide, [
    "Pembangunan web apps telah siap dari sudut fungsi utama.",
    "Fasa semasa tertumpu kepada minor polish, testing dan deployment readiness.",
    "Fasa seterusnya ialah setup native apps dan submission ke App Store serta Play Store.",
  ], 102, 452, 680, 18, colors.text, 34);

  addText(slide, "Tarikh mula projek", { left: 960, top: 110, width: 220, height: 22 }, { fontSize: 15, color: "#B9CAD6" });
  addText(slide, "14 Mei 2026", { left: 960, top: 136, width: 220, height: 36 }, { fontSize: 28, bold: true, color: colors.white });
  addText(slide, "Status setakat", { left: 960, top: 210, width: 220, height: 22 }, { fontSize: 15, color: "#B9CAD6" });
  addText(slide, "4 Ogos 2026", { left: 960, top: 236, width: 220, height: 36 }, { fontSize: 28, bold: true, color: colors.white });
  addText(slide, "Nilai projek", { left: 960, top: 310, width: 220, height: 22 }, { fontSize: 15, color: "#B9CAD6" });
  addText(slide, "RM35,200", { left: 960, top: 336, width: 220, height: 36 }, { fontSize: 30, bold: true, color: colors.white });
  addText(slide, "Bayaran diterima", { left: 960, top: 412, width: 220, height: 22 }, { fontSize: 15, color: "#B9CAD6" });
  addText(slide, "RM13,866.67", { left: 960, top: 438, width: 250, height: 36 }, { fontSize: 30, bold: true, color: "#A9E0D8" });
  addText(slide, "Baki semasa", { left: 960, top: 512, width: 220, height: 22 }, { fontSize: 15, color: "#B9CAD6" });
  addText(slide, "RM21,333.33", { left: 960, top: 538, width: 250, height: 36 }, { fontSize: 30, bold: true, color: "#FFD59A" });

  addText(slide, "Disediakan untuk semakan pihak pengurusan dan penjadualan kutipan sebelum muktamar.", { left: 72, top: 632, width: 740, height: 24 }, { fontSize: 15, color: colors.muted });
  return slide;
}

function slide2(presentation) {
  const slide = presentation.slides.add();
  slide.background.fill = colors.white;
  addHeaderBand(slide, "Timeline Projek Berdasarkan Modul Sebenar", "Audit dibuat daripada struktur modul, routes, controllers, pages Vue dan dokumen status projek.");

  const timelineLeft = 338;
  const timelineTop = 190;
  const rowHeight = 54;
  const colWidths = [110, 110, 110, 110, 110, 140];
  const colLabels = ["14-31 Mei", "1-15 Jun", "16-30 Jun", "1-15 Jul", "16-31 Jul", "1 Ogos - 9 Sep"];
  const rows = [
    ["Asas sistem", [0, 1], colors.softBlue, "Setup stack, DB, role & organisasi"],
    ["Core membership", [1, 2], colors.softTeal, "Register, OTP, profile, card, directory"],
    ["Member modules", [2, 3], colors.softGold, "Announcements, library, events, usrah, infaq"],
    ["Commerce & engagement", [3, 3], colors.softRose, "Products, referral, polls, video, popup"],
    ["Admin & finance", [3, 4], colors.softBlue, "Import ahli, yuran, laporan, dashboard"],
    ["Finalisation web", [4, 5], colors.softTeal, "QA, bug fixing, polish, testing"],
    ["Native apps", [5, 5], colors.softGold, "Setup wrapper, packaging, store submission"],
  ];

  addText(slide, "Pecahan Modul", { left: 64, top: 150, width: 220, height: 30 }, { fontSize: 20, bold: true, color: colors.ink });
  addBulletList(slide, [
    "Auth, OTP, onboarding & profile",
    "Member card, directory & transition",
    "Events, QR attendance & usrah",
    "Infaq, yuran, finance dashboard",
    "Products, orders & referral system",
    "Broadcast, polls, popups & content hub",
  ], 64, 196, 236, 16, colors.text, 31);

  colLabels.forEach((label, index) => {
    const left = timelineLeft + colWidths.slice(0, index).reduce((a, b) => a + b, 0);
    addBox(slide, {
      geometry: "rect",
      position: { left, top: 146, width: colWidths[index], height: 32 },
      fill: index === 5 ? colors.ink : "#F3F6F8",
      line: { style: "solid", fill: colors.line, width: 1 },
    });
    addText(slide, label, { left: left + 8, top: 153, width: colWidths[index] - 16, height: 20 }, { fontSize: 13, bold: true, color: index === 5 ? colors.white : colors.text, align: "center" });
  });

  rows.forEach((row, rowIndex) => {
    const top = timelineTop + rowIndex * rowHeight;
    addText(slide, row[0], { left: 338 - 148, top: top + 13, width: 134, height: 24 }, { fontSize: 17, bold: true, color: colors.ink, align: "right" });
    addRule(slide, timelineLeft, top + rowHeight - 7, 690, "#EEF2F5", 1);

    let x = timelineLeft;
    colWidths.forEach((w) => {
      addRule(slide, x, top - 8, 1, rowHeight + 8, colors.line, rowHeight + 8);
      x += w;
    });

    const [startIdx, endIdx] = row[1];
    const barLeft = timelineLeft + colWidths.slice(0, startIdx).reduce((a, b) => a + b, 0) + 8;
    const barWidth = colWidths.slice(startIdx, endIdx + 1).reduce((a, b) => a + b, 0) - 16;
    addBox(slide, {
      position: { left: barLeft, top: top + 6, width: barWidth, height: 32 },
      fill: row[2],
      line: { style: "solid", fill: "none", width: 0 },
      borderRadius: "rounded-full",
    });
    addText(slide, row[3], { left: barLeft + 12, top: top + 13, width: barWidth - 24, height: 18 }, { fontSize: 13, color: colors.ink, bold: true });
  });

  addBox(slide, {
    position: { left: 64, top: 590, width: 1152, height: 80 },
    fill: "#F7F9FA",
    line: { style: "solid", fill: colors.line, width: 1 },
    borderRadius: "rounded-2xl",
  });
  addText(slide, "Rumusan status", { left: 92, top: 614, width: 180, height: 24 }, { fontSize: 18, bold: true, color: colors.ink });
  addText(slide, "Web apps telah siap dari segi fungsi utama. Tempoh semasa digunakan untuk minor polish dan testing, manakala minggu berikutnya difokuskan kepada native app setup, packaging, dan submission ke App Store serta Play Store sebelum buffer semakan muktamar.", { left: 240, top: 610, width: 930, height: 38 }, { fontSize: 16, color: colors.text });
  return slide;
}

function progressBar(slide, left, top, width, paidPct, label, total, paid, balance, tint) {
  addText(slide, label, { left, top, width: 170, height: 24 }, { fontSize: 18, bold: true, color: colors.ink });
  addText(slide, `Jumlah: RM${money(total)}`, { left: left + 176, top: top + 1, width: 210, height: 22 }, { fontSize: 15, color: colors.muted });
  addBox(slide, {
    geometry: "rect",
    position: { left, top: top + 34, width, height: 18 },
    fill: "#E9EEF2",
    line: { style: "solid", fill: "none", width: 0 },
  });
  addBox(slide, {
    geometry: "rect",
    position: { left, top: top + 34, width: Math.max(16, width * paidPct), height: 18 },
    fill: tint,
    line: { style: "solid", fill: "none", width: 0 },
  });
  addText(slide, `Telah dibayar: RM${money(paid)}`, { left, top: top + 62, width: 250, height: 20 }, { fontSize: 15, color: colors.text });
  addText(slide, `Baki: RM${money(balance)}`, { left: left + 260, top: top + 62, width: 220, height: 20 }, { fontSize: 15, color: colors.text });
}

function slide3(presentation) {
  const slide = presentation.slides.add();
  slide.background.fill = colors.white;
  addHeaderBand(slide, "Status Bayaran dan Cadangan Ansuran", "Pecahan semasa menunjukkan baki RM21,333.33 yang dicadangkan selesai sebelum muktamar September 2026.");

  const total = 35200;
  const paid = 13866.67;
  const balance = total - paid;

  addBox(slide, {
    position: { left: 64, top: 156, width: 348, height: 188 },
    fill: colors.ink,
    line: { style: "solid", fill: "none", width: 0 },
    borderRadius: "rounded-2xl",
  });
  addText(slide, "Ringkasan Kewangan", { left: 92, top: 184, width: 220, height: 24 }, { fontSize: 20, bold: true, color: colors.white });
  addText(slide, "Nilai Projek", { left: 92, top: 230, width: 120, height: 20 }, { fontSize: 14, color: "#BCD0DD" });
  addText(slide, `RM${money(total)}`, { left: 92, top: 250, width: 220, height: 30 }, { fontSize: 30, bold: true, color: colors.white });
  addText(slide, "Bayaran Diterima", { left: 92, top: 294, width: 140, height: 20 }, { fontSize: 14, color: "#BCD0DD" });
  addText(slide, `RM${money(paid)}`, { left: 92, top: 314, width: 220, height: 30 }, { fontSize: 30, bold: true, color: "#A9E0D8" });
  addText(slide, "Baki Semasa", { left: 92, top: 358, width: 110, height: 20 }, { fontSize: 14, color: "#BCD0DD" });
  addText(slide, `RM${money(balance)}`, { left: 92, top: 378, width: 220, height: 30 }, { fontSize: 30, bold: true, color: "#FFD59A" });

  addBox(slide, {
    position: { left: 444, top: 156, width: 772, height: 246 },
    fill: "#FAFBFC",
    line: { style: "solid", fill: colors.line, width: 1 },
    borderRadius: "rounded-2xl",
  });
  addText(slide, "Kemajuan Bayaran Mengikut Pihak", { left: 472, top: 184, width: 320, height: 24 }, { fontSize: 20, bold: true, color: colors.ink });
  progressBar(slide, 472, 226, 650, 10000 / 17600, "WADAH", 17600, 10000, 7600, colors.teal);
  progressBar(slide, 472, 294, 650, 2346.67 / 14080, "ABIM", 14080, 2346.67, 11733.33, colors.gold);
  progressBar(slide, 472, 362, 650, 1520 / 3520, "PKPIM", 3520, 1520, 2000, colors.coral);

  addBox(slide, {
    position: { left: 64, top: 432, width: 1152, height: 220 },
    fill: colors.white,
    line: { style: "solid", fill: colors.line, width: 1 },
    borderRadius: "rounded-2xl",
  });
  addText(slide, "Cadangan Jadual Ansuran Sebelum Muktamar", { left: 92, top: 458, width: 430, height: 26 }, { fontSize: 22, bold: true, color: colors.ink });

  const headers = ["Tarikh", "Pihak", "Cadangan Bayaran", "Rasional"];
  const rows = [
    ["15 Ogos 2026", "WADAH", "RM7,600.00", "Menjelaskan baki selepas web apps siap dan native setup bermula"],
    ["22 Ogos 2026", "PKPIM", "RM2,000.00", "Menyelesaikan baki semasa fasa build dan packaging"],
    ["31 Ogos 2026", "ABIM", "RM5,866.67", "Bayaran pertama sebelum submission store selesai"],
    ["7 September 2026", "ABIM", "RM5,866.66", "Bayaran akhir sebelum buffer muktamar"],
  ];

  const colX = [92, 252, 402, 618];
  const colW = [132, 122, 176, 548];
  headers.forEach((header, index) => {
    addBox(slide, {
      geometry: "rect",
      position: { left: colX[index], top: 500, width: colW[index], height: 34 },
      fill: "#EEF3F6",
      line: { style: "solid", fill: colors.line, width: 1 },
    });
    addText(slide, header, { left: colX[index] + 8, top: 508, width: colW[index] - 16, height: 18 }, { fontSize: 14, bold: true, color: colors.ink, align: index === 2 ? "center" : "left" });
  });

  rows.forEach((row, rIndex) => {
    const y = 534 + rIndex * 28;
    row.forEach((cell, cIndex) => {
      addText(slide, cell, { left: colX[cIndex] + 8, top: y + 6, width: colW[cIndex] - 16, height: 18 }, {
        fontSize: 13,
        color: colors.text,
        bold: cIndex === 1 || cIndex === 2,
        align: cIndex === 2 ? "center" : "left",
      });
    });
    addRule(slide, 92, y + 28, 1084, "#EDF1F4", 1);
  });
  return slide;
}

async function main() {
  await fs.mkdir(OUT_DIR, { recursive: true });
  const presentation = Presentation.create({ slideSize });
  slide1(presentation);
  slide2(presentation);
  slide3(presentation);

  for (const [index, slide] of presentation.slides.items.entries()) {
    const stem = `slide-${String(index + 1).padStart(2, "0")}`;
    const png = await presentation.export({ slide, format: "png", scale: 1 });
    await writeBlob(`${OUT_DIR}/${stem}.png`, png);
  }

  const pptx = await PresentationFile.exportPptx(presentation);
  await pptx.save(FINAL_PPTX);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
