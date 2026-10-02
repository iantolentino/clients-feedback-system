const copyDialog = document.getElementById('save-copy-dialog');

function openSaveCopy() {
  copyDialog.showModal();
}

function closeSaveCopy() {
  copyDialog.close();
}

copyDialog.addEventListener('click', event => {
  if (event.target === copyDialog) closeSaveCopy();
});

function saveFeedbackPdf() {
  closeSaveCopy();
  if (!window.pdfMake) {
    alert('PDF download is unavailable. Please refresh the page and try again.');
    return;
  }
  const content = [
    { text: 'STRATA STAFF GLOBAL', style: 'brand' },
    { text: 'Client feedback', style: 'heading' }
  ];
  for (const row of feedbackRows()) {
    content.push({
      stack: [
        { text: row.title, style: 'label' },
        { text: row.value || '—', style: 'answer' }
      ],
      margin: [0, 0, 0, 12]
    });
  }
  window.pdfMake.createPdf({
    pageSize: 'A4',
    pageMargins: [48, 52, 48, 52],
    content,
    styles: {
      brand: { fontSize: 10, bold: true, color: '#205798', margin: [0, 0, 0, 8] },
      heading: { fontSize: 20, bold: true, color: '#17232f', margin: [0, 0, 0, 20] },
      label: { fontSize: 10, bold: true, color: '#17232f', margin: [0, 0, 0, 4] },
      answer: { fontSize: 10, color: '#52606d', lineHeight: 1.3 }
    },
    defaultStyle: { font: 'Roboto' },
    footer: (page, pages) => ({
      text: 'Page ' + page + ' of ' + pages,
      alignment: 'right',
      margin: [0, 0, 48, 0],
      fontSize: 9,
      color: '#71808c'
    })
  }).download('client-feedback.pdf');
}

function feedbackRows() {
  const detail = document.querySelector('.detail');
  const text = node => node?.innerText?.trim() || '';
  const rows = [{ title: 'Client', value: text(detail.querySelector('h1')) }];
  detail.querySelectorAll('.detail-grid div').forEach(item => rows.push({
    title: text(item.querySelector('span')),
    value: text(item.querySelector('strong'))
  }));
  detail.querySelectorAll('.response-list div').forEach(item => rows.push({
    title: text(item.querySelector('span')),
    value: text(item.querySelector('strong'))
  }));
  detail.querySelectorAll('.written-response').forEach(item => rows.push({
    title: text(item.querySelector('h3')),
    value: text(item.querySelector('p'))
  }));
  return rows;
}

function saveFeedbackImage() {
  const width = 1200, padding = 58, maxWidth = width - padding * 2;
  const canvas = document.createElement('canvas');
  canvas.width = width;
  const context = canvas.getContext('2d');
  if (!context) {
    alert('Image export is unavailable in this browser. Please use Save as PDF.');
    return;
  }

  // Wrap by measured width, including very long words with no spaces.
  const wrap = (value, font) => {
    context.font = font;
    const lines = [];
    for (const paragraph of String(value || '—').split(/\r?\n/)) {
      let line = '';
      for (const word of paragraph.split(/\s+/)) {
        if (!word) continue;
        const candidate = line ? line + ' ' + word : word;
        if (context.measureText(candidate).width <= maxWidth) {
          line = candidate;
          continue;
        }
        if (line) { lines.push(line); line = ''; }
        for (const character of word) {
          if (line && context.measureText(line + character).width > maxWidth) {
            lines.push(line); line = '';
          }
          line += character;
        }
      }
      lines.push(line);
    }
    return lines;
  };

  const items = feedbackRows().map(row => ({
    title: wrap(row.title, 'bold 17px Arial'),
    value: wrap(row.value, '16px Arial')
  }));
  const lineHeight = 27;
  const height = padding + 76 + items.reduce((sum, item) =>
    sum + item.title.length * lineHeight + item.value.length * lineHeight + 26, 0) + padding;
  if (height > 16000) {
    alert('This response is too long for a single image. Please choose Save as PDF.');
    return;
  }

  canvas.height = height;
  context.fillStyle = '#fff';
  context.fillRect(0, 0, width, height);
  let y = padding;
  context.fillStyle = '#205798';
  context.font = 'bold 21px Arial';
  context.fillText('STRATA STAFF GLOBAL | CLIENT FEEDBACK', padding, y);
  y += 58;
  for (const item of items) {
    context.fillStyle = '#17232f';
    context.font = 'bold 17px Arial';
    for (const line of item.title) { context.fillText(line, padding, y); y += lineHeight; }
    context.fillStyle = '#52606d';
    context.font = '16px Arial';
    for (const line of item.value) { context.fillText(line, padding, y); y += lineHeight; }
    y += 26;
  }
  canvas.toBlob(blob => {
    if (!blob) {
      alert('Image export failed. Please use Save as PDF.');
      return;
    }
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'client-feedback.png';
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
    closeSaveCopy();
  }, 'image/png');
}
