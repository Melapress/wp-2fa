
document.addEventListener('DOMContentLoaded', function() {
  const button = document.getElementById('mlp-elements-button');
  const flyout = document.getElementById('mlp-flyout');
  const overlay = document.getElementById('mlp-overlay');

  if (button && flyout && overlay) {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      flyout.classList.toggle('opened');
      overlay.style.display = overlay.style.display === 'none' || overlay.style.display === '' ? 'block' : 'none';
      button.setAttribute('aria-expanded', flyout.classList.contains('opened') ? 'true' : 'false');
    });

    overlay.addEventListener('click', function(e) {
      e.preventDefault();
      overlay.style.display = 'none';
      flyout.classList.remove('opened');
      button.setAttribute('aria-expanded', 'false');
    });
  }
});
  