const navItems = document.querySelectorAll("footer .wp-block-navigation-submenu");

// 1. 各メニュー項目に対してホバーイベントを設定
navItems.forEach((item) => {
  item.addEventListener("mouseenter", () => {
    item.classList.add("mb");
    const submenu = item.querySelector(".wp-block-navigation__submenu-container");
    if (submenu) {
      submenu.classList.add("appear");
    }
  });

  item.addEventListener("mouseleave", () => {
    item.classList.remove("mb");
    const submenu = item.querySelector(".wp-block-navigation__submenu-container");
    if (submenu) {
      submenu.classList.remove("appear");
    }
  });
});

const HEADER_OFFSET = 150;
const DRAWER_MAX_WIDTH = 599;

let lockedScrollY = 0;
let isScrollLocked = false;

const clearBodyScrollLockStyles = () => {
  document.body.style.position = "";
  document.body.style.top = "";
  document.body.style.left = "";
  document.body.style.right = "";
  document.body.style.width = "";
};

const lockBodyScroll = () => {
  if (isScrollLocked || window.innerWidth > DRAWER_MAX_WIDTH) return;
  lockedScrollY = window.scrollY;
  isScrollLocked = true;
  document.body.style.position = "fixed";
  document.body.style.top = `-${lockedScrollY}px`;
  document.body.style.left = "0";
  document.body.style.right = "0";
  document.body.style.width = "100%";
};

const unlockBodyScroll = (restorePosition = true) => {
  if (!isScrollLocked) return lockedScrollY;
  const y = lockedScrollY;
  isScrollLocked = false;
  clearBodyScrollLockStyles();
  if (restorePosition) {
    window.scrollTo(0, y);
  }
  return y;
};

const isDrawerOpen = () =>
  Boolean(
    document.querySelector(
      "header .wp-block-navigation__responsive-container.is-menu-open"
    )
  );

const closeDrawers = () => {
  document
    .querySelectorAll("header .wp-block-navigation__responsive-container")
    .forEach((drawer) => {
      drawer.classList.remove("is-menu-open", "has-modal-open");
    });
  document.documentElement.classList.remove("has-modal-open");
};

const getSamePageTarget = (anchor) => {
  if (!anchor || !anchor.hash || anchor.hash === "#") return null;

  try {
    const url = new URL(anchor.href, window.location.href);
    if (url.origin !== window.location.origin) return null;

    const currentPath = window.location.pathname.replace(/\/$/, "") || "/";
    const linkPath = url.pathname.replace(/\/$/, "") || "/";
    if (currentPath !== linkPath) return null;

    return document.querySelector(url.hash);
  } catch (error) {
    return null;
  }
};

const prefersReducedMotion = () =>
  window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const smoothScrollToElement = (target) => {
  if (!target) return;
  const top =
    target.getBoundingClientRect().top + window.scrollY - HEADER_OFFSET;
  window.scrollTo({
    top: Math.max(0, top),
    behavior: prefersReducedMotion() ? "auto" : "smooth",
  });
};

window.addEventListener("resize", function () {
  if (window.innerWidth >= 600) {
    closeDrawers();
    unlockBodyScroll(true);
  }
});

// ばつボタンをハンバーガーと同じ位置に合わせる
(function () {
  const syncCloseButtonPosition = () => {
    document.querySelectorAll("header .wp-block-navigation").forEach((nav) => {
      const openBtn = nav.querySelector(
        ".wp-block-navigation__responsive-container-open"
      );
      const closeBtn = nav.querySelector(
        ".wp-block-navigation__responsive-container-close"
      );
      if (!openBtn || !closeBtn) return;

      const rect = openBtn.getBoundingClientRect();
      closeBtn.style.position = "fixed";
      closeBtn.style.top = `${rect.top}px`;
      closeBtn.style.right = `${Math.max(0, window.innerWidth - rect.right)}px`;
      closeBtn.style.left = "auto";
      closeBtn.style.bottom = "auto";
      closeBtn.style.width = `${rect.width}px`;
      closeBtn.style.height = `${rect.height}px`;
    });
  };

  syncCloseButtonPosition();
  window.addEventListener("resize", syncCloseButtonPosition);
  window.addEventListener("scroll", syncCloseButtonPosition, { passive: true });

  document.addEventListener(
    "click",
    (event) => {
      if (
        event.target.closest(
          ".wp-block-navigation__responsive-container-open, .wp-block-navigation__responsive-container-close"
        )
      ) {
        requestAnimationFrame(syncCloseButtonPosition);
        setTimeout(syncCloseButtonPosition, 50);
      }
    },
    true
  );
})();

// 599px以下：ドロワー表示中は背景スクロールを止め、閉じたら同じ位置から再開
(function () {
  const drawers = document.querySelectorAll(
    "header .wp-block-navigation__responsive-container"
  );
  if (!drawers.length) return;

  const syncLockWithDrawer = () => {
    if (window.innerWidth > DRAWER_MAX_WIDTH) {
      unlockBodyScroll(true);
      return;
    }
    if (isDrawerOpen()) {
      lockBodyScroll();
    } else {
      unlockBodyScroll(true);
    }
  };

  drawers.forEach((drawer) => {
    const observer = new MutationObserver(syncLockWithDrawer);
    observer.observe(drawer, { attributes: true, attributeFilter: ["class"] });
  });

  document.addEventListener(
    "click",
    (event) => {
      if (
        event.target.closest(
          ".wp-block-navigation__responsive-container-open, .wp-block-navigation__responsive-container-close"
        )
      ) {
        setTimeout(syncLockWithDrawer, 10);
        setTimeout(syncLockWithDrawer, 100);
      }
    },
    true
  );
})();

// スムーススクロール + ヘッダー分オフセット（約150px）
(function () {
  document.addEventListener("click", (event) => {
    const anchor = event.target.closest('a[href*="#"]');
    if (!anchor) return;

    const target = getSamePageTarget(anchor);
    if (!target) return;

    event.preventDefault();

    const hash = new URL(anchor.href, window.location.href).hash;

    if (isDrawerOpen() && window.innerWidth <= DRAWER_MAX_WIDTH) {
      // fixed解除前の位置でターゲット座標を計算し、同じ位置から目的地へスムース移動
      const top =
        target.getBoundingClientRect().top + lockedScrollY - HEADER_OFFSET;
      closeDrawers();
      isScrollLocked = false;
      clearBodyScrollLockStyles();
      window.scrollTo({
        top: Math.max(0, top),
        behavior: prefersReducedMotion() ? "auto" : "smooth",
      });
    } else {
      smoothScrollToElement(target);
    }

    if (hash) {
      history.pushState(null, "", hash);
    }
  });

  // ページ読み込み時のハッシュもヘッダー分ずらす
  window.addEventListener("load", () => {
    if (!window.location.hash) return;
    const target = document.querySelector(window.location.hash);
    if (!target) return;
    requestAnimationFrame(() => {
      smoothScrollToElement(target);
    });
  });
})();
