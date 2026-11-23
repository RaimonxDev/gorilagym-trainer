/* GorilaGym · perfil: foto de perfil recortada y reducida en el celular antes de subir */
(function () {
  'use strict';
  const GG = window.GG;
  const form = document.querySelector('[data-avatar-form]');
  if (!form) return;
  const pick = form.querySelector('[data-avatar-input]');
  const final = form.querySelector('[data-avatar-final]');
  const prev = form.querySelector('[data-avatar-preview]');
  pick.addEventListener('change', () => {
    const f = pick.files && pick.files[0];
    if (!f) return;
    if (!/^image\//.test(f.type)) { GG.toast('Elige una imagen'); return; }
    const url = URL.createObjectURL(f);
    const img = new Image();
    img.onload = () => {
      const lado = Math.min(img.naturalWidth, img.naturalHeight);
      const c = document.createElement('canvas');
      c.width = c.height = 512;
      c.getContext('2d').drawImage(img, (img.naturalWidth - lado) / 2, (img.naturalHeight - lado) / 2, lado, lado, 0, 0, 512, 512);
      URL.revokeObjectURL(url);
      c.toBlob((blob) => {
        if (!blob) { GG.toast('No se pudo procesar la foto'); return; }
        const dt = new DataTransfer();
        dt.items.add(new File([blob], 'perfil.jpg', { type: 'image/jpeg' }));
        final.files = dt.files;
        prev.innerHTML = '<img src="' + c.toDataURL('image/jpeg', 0.7) + '" alt="" class="w-20 h-20 rounded-full object-cover">';
        GG.toast('Subiendo…');
        form.submit();
      }, 'image/jpeg', 0.88);
    };
    img.onerror = () => { URL.revokeObjectURL(url); GG.toast('Formato no soportado. Prueba con otra foto.'); };
    img.src = url;
  });
})();
