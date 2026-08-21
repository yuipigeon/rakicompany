
////////////////////////////////////////////////////////
// 390以下は同じ幅を固定 //
////////////////////////////////////////////////////////
document.addEventListener("DOMContentLoaded", function () {
  const viewport = document.querySelector('meta[name="viewport"]');

  function getViewportWidth() {
      if (window.visualViewport && window.visualViewport.width) {
          return window.visualViewport.width;
      }
      return window.innerWidth || document.documentElement.clientWidth;
  }

  function switchViewport() {
      if (!viewport) return;
      const value = getViewportWidth() <= 390
          ? 'width=390'
          : 'width=device-width,initial-scale=1';
      if (viewport.getAttribute('content') !== value) {
          viewport.setAttribute('content', value);
      }
  }
  if (viewport) {
      window.addEventListener('resize', switchViewport, false);
      switchViewport();
  }
});


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

const DRAWER_MAX_WIDTH = 599;

(function () {
  const header = document.querySelector(
    ".wp-site-blocks > .wp-block-template-part"
  );
  if (!header) return;

  const setHeaderHeight = () => {
    const cover = document.querySelector(
      ".is-frontend .wp-block-cover:has(.p-news)"
    );
    const headerH = cover
      ? cover.getBoundingClientRect().top + window.scrollY
      : header.offsetHeight;
    document.documentElement.style.setProperty(
      "--header-height",
      `${Math.max(0, headerH)}px`
    );
  };

  setHeaderHeight();
  window.addEventListener("resize", setHeaderHeight);
  window.addEventListener("load", setHeaderHeight);
})();

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
  document.querySelectorAll("header .wp-block-navigation").forEach((nav) => {
    const drawer = nav.querySelector(
      ".wp-block-navigation__responsive-container"
    );
    if (!drawer) return;
    drawer.classList.remove("is-menu-open", "has-modal-open");
    drawer.setAttribute("aria-hidden", "true");
    const openBtn = nav.querySelector(
      ".wp-block-navigation__responsive-container-open"
    );
    if (openBtn) {
      openBtn.setAttribute("aria-expanded", "false");
    }
  });
  document.documentElement.classList.remove("has-modal-open");
  document.body.classList.remove("has-modal-open");
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

const getNavOffset = () => {
  const nav = document.querySelector("header .wp-block-navigation");
  if (!nav) return 80;
  const height = nav.getBoundingClientRect().height;
  return Math.ceil(height || 80) + 16;
};

const smoothScrollToElement = (target) => {
  if (!target) return;
  const top =
    target.getBoundingClientRect().top + window.scrollY - getNavOffset();
  window.scrollTo({
    top: Math.max(0, top),
    behavior: prefersReducedMotion() ? "auto" : "smooth",
  });
};

const desktopDrawerMq = window.matchMedia("(min-width: 600px)");
const closeDrawerIfDesktop = () => {
  if (!desktopDrawerMq.matches) return;
  if (!isDrawerOpen()) return;
  closeDrawers();
  unlockBodyScroll(true);
};
if (desktopDrawerMq.addEventListener) {
  desktopDrawerMq.addEventListener("change", closeDrawerIfDesktop);
} else if (desktopDrawerMq.addListener) {
  desktopDrawerMq.addListener(closeDrawerIfDesktop);
}
window.addEventListener("resize", closeDrawerIfDesktop);

// タイトルは流し、ナビだけ画面上端に固定
(function () {
  const nav = document.querySelector("header .wp-block-navigation");
  if (!nav) return;

  const placeholder = document.createElement("div");
  placeholder.className = "js-nav-placeholder";
  placeholder.setAttribute("aria-hidden", "true");
  nav.parentNode.insertBefore(placeholder, nav);

  let isFixed = false;
  let triggerY = 0;
  let left = 0;
  let width = 0;
  let height = 0;

  const isFullBleedNav = () =>
    window.matchMedia("(min-width: 600px) and (max-width: 768px)").matches;

  const readNavBox = () => {
    const rect = nav.getBoundingClientRect();
    const inner =
      nav.querySelector(".wp-block-navigation__container") ||
      nav.querySelector(".wp-block-navigation__responsive-container-content");
    const innerHeight = inner ? inner.getBoundingClientRect().height : 0;
    return {
      left: rect.left,
      width: rect.width,
      height: Math.max(rect.height, innerHeight, nav.scrollHeight, 0),
    };
  };

  const pin = () => {
    nav.classList.add("is-nav-fixed");
    nav.style.position = "fixed";
    nav.style.top = "0";
    nav.style.height = "";
    nav.style.zIndex = "9999";
    if (isFullBleedNav()) {
      nav.style.left = "0";
      nav.style.right = "0";
      nav.style.width = "100%";
    } else {
      nav.style.left = `${left}px`;
      nav.style.right = "";
      nav.style.width = `${width}px`;
    }
    placeholder.style.display = "block";
    placeholder.style.width = isFullBleedNav() ? "100%" : `${width}px`;
    placeholder.style.height = `${Math.max(height, 1)}px`;
  };

  const unpin = () => {
    nav.classList.remove("is-nav-fixed");
    nav.style.position = "";
    nav.style.top = "";
    nav.style.left = "";
    nav.style.right = "";
    nav.style.width = "";
    nav.style.height = "";
    nav.style.zIndex = "";
    placeholder.style.display = "none";
    placeholder.style.width = "";
    placeholder.style.height = "";
  };

  const measureInFlow = () => {
    const box = readNavBox();
    left = box.left;
    width = box.width;
    height = box.height;
    triggerY = nav.getBoundingClientRect().top + window.scrollY;
  };

  const onScroll = () => {
    if (window.scrollY >= triggerY) {
      if (!isFixed) {
        const box = readNavBox();
        left = box.left;
        width = box.width;
        height = box.height;
        isFixed = true;
        pin();
      }
    } else if (isFixed) {
      isFixed = false;
      unpin();
    }
  };

  const refresh = () => {
    if (isFixed) {
      isFixed = false;
      unpin();
    }
    measureInFlow();
    onScroll();
  };

  refresh();
  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("resize", refresh);
})();

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

// 599px以下：リンク以外（オーバーレイ・余白）をタップしたらドロワーを閉じる
(function () {
  document.addEventListener("click", (event) => {
    if (!isDrawerOpen() || window.innerWidth > DRAWER_MAX_WIDTH) return;

    const drawer = event.target.closest(
      "header .wp-block-navigation__responsive-container.is-menu-open"
    );
    if (!drawer) return;

    if (
      event.target.closest(
        ".wp-block-navigation__responsive-container-close, .wp-block-navigation__responsive-container-open"
      )
    ) {
      return;
    }

    if (event.target.closest("a")) return;

    if (
      event.target.closest(
        ".wp-block-navigation-submenu__toggle, .wp-block-navigation__submenu-icon"
      )
    ) {
      return;
    }

    closeDrawers();
    unlockBodyScroll(true);
  });
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
        target.getBoundingClientRect().top + lockedScrollY - getNavOffset();
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
