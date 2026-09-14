    <div class="fixed bottom-6 right-6 z-50 transform translate-y-16 opacity-0 pointer-events-none transition-all duration-200 bg-inverse-surface text-inverse-on-surface px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 font-label-md text-label-md" id="toastNotification">
      <span class="material-symbols-outlined text-[18px] text-tertiary-fixed">check_circle</span>
      <span id="toastMessage">Notification message</span>
    </div>
  </main>
</div>

<script>
  function showGlobalToast(text, duration = 2400) {
    const toast = document.getElementById('toastNotification');
    const toastMessage = document.getElementById('toastMessage');
    if (!toast || !toastMessage) return;
    toastMessage.textContent = text;
    toast.classList.remove('translate-y-16', 'opacity-0', 'pointer-events-none');
    setTimeout(() => {
      toast.classList.add('translate-y-16', 'opacity-0', 'pointer-events-none');
    }, duration);
  }
</script>
</body>
</html>
