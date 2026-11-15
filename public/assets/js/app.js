'use strict';

document.addEventListener('click', function (event) {
  const confirmButton = event.target.closest('[data-confirm]');
  if (confirmButton) {
    const message = confirmButton.getAttribute('data-confirm') || 'Lanjutkan tindakan ini?';
    if (!window.confirm(message)) event.preventDefault();
  }
});

document.addEventListener('DOMContentLoaded', function () {
  const dateInput = document.querySelector('[data-availability-date]');
  const timeSelect = document.querySelector('[data-availability-time]');
  const packageInput = document.querySelector('[data-availability-package]');
  const message = document.querySelector('[data-availability-message]');
  if (!dateInput || !timeSelect || !packageInput) return;

  async function loadAvailability() {
    timeSelect.innerHTML = '<option value="">Memuat slot tersedia...</option>';
    timeSelect.disabled = true;
    if (message) message.textContent = '';
    if (!dateInput.value) {
      timeSelect.innerHTML = '<option value="">Pilih tanggal dahulu</option>';
      return;
    }
    try {
      const params = new URLSearchParams({ r: 'availability', date: dateInput.value, package_id: packageInput.value });
      const response = await fetch('index.php?' + params.toString(), { headers: { 'Accept': 'application/json' } });
      const data = await response.json();
      timeSelect.replaceChildren();
      const first = document.createElement('option');
      first.value = '';
      first.textContent = data.slots && data.slots.length ? 'Pilih jam pemotretan' : 'Tidak ada slot tersedia';
      timeSelect.appendChild(first);
      (data.slots || []).forEach(function (slot) {
        const option = document.createElement('option');
        option.value = slot.value;
        option.textContent = slot.label;
        timeSelect.appendChild(option);
      });
      timeSelect.disabled = !(data.slots && data.slots.length);
      if (message) message.textContent = data.message || ((data.slots || []).length ? 'Slot diperiksa kembali oleh server saat reservasi dikirim.' : 'Silakan pilih tanggal lainnya.');
    } catch (error) {
      timeSelect.innerHTML = '<option value="">Ketersediaan tidak dapat dimuat</option>';
      if (message) message.textContent = 'Periksa koneksi lalu muat ulang halaman.';
    }
  }
  dateInput.addEventListener('change', loadAvailability);
  if (dateInput.value) loadAvailability();
});
