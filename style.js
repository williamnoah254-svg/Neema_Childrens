  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  const header = document.querySelector('.site-header');

  if (toggle && nav && header) {
    function setMenuOpen(isOpen) {
      nav.classList.toggle('open', isOpen);
      header.classList.toggle('menu-open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
      toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    }

    toggle.addEventListener('click', (event) => {
      event.stopPropagation();
      setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('click', (event) => {
      if (!header.contains(event.target)) setMenuOpen(false);
      if (nav.contains(event.target) && event.target.closest('a')) setMenuOpen(false);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setMenuOpen(false);
        toggle.focus();
      }
    });
  }

  const galleryFilters = document.querySelectorAll('.gallery-filter-list [data-gallery-filter]');
  const galleryItems = document.querySelectorAll('.gallery-item[data-category]');
  galleryFilters.forEach((filter) => {
    filter.addEventListener('click', (event) => {
      event.preventDefault();
      const category = filter.dataset.galleryFilter;
      galleryFilters.forEach((item) => item.classList.toggle('active', item === filter));
      galleryItems.forEach((item) => {
        item.hidden = category !== 'all' && item.dataset.category !== category;
      });
    });
  });
