<script>
import { VerticalNav } from '@layouts/components';
import { useLayoutConfigStore } from '@layouts/stores/config';

export default defineComponent({
  props: {
    navItems: {
      type: Array,
      required: true,
    },
    verticalNavAttrs: {
      type: Object,
      default: () => ({}),
    },
  },
  setup(props, { slots }) {
    const route = useRoute()
    const { width: windowWidth } = useWindowSize()
    const configStore = useLayoutConfigStore()
    const isOverlayNavActive = ref(false)
    const isLayoutOverlayVisible = ref(false)
    const toggleIsOverlayNavActive = useToggle(isOverlayNavActive)


    // ℹ️ This is alternative to below two commented watcher
    // We want to show overlay if overlay nav is visible and want to hide overlay if overlay is hidden and vice versa.
    syncRef(isOverlayNavActive, isLayoutOverlayVisible)

    // watch(isOverlayNavActive, value => {
    //   // Sync layout overlay with overlay nav
    //   isLayoutOverlayVisible.value = value
    // })
    // watch(isLayoutOverlayVisible, value => {
    //   // If overlay is closed via click, close hide overlay nav
    //   if (!value) isOverlayNavActive.value = false
    // })
    // ℹ️ Hide overlay if user open overlay nav in <md and increase the window width without closing overlay nav
    watch(windowWidth, () => {
      if (!configStore.isLessThanOverlayNavBreakpoint && isLayoutOverlayVisible.value)
        isLayoutOverlayVisible.value = false
    })

    watch(
      () => route.name, // Watch the current route's name
      (newRouteName) => {
        const layoutContent = document.querySelector('.layout-content-wrapper');
        
        if (layoutContent) {
          let whiteBgPages = [
            'client-account',
            'client-preferences',
            'agent-account',
            'agent-preferences',
            'admin-account',
            'admin-preferences',
            'admin-clients-add-client',
            'admin-agents-add-agent',
            'admin-admins-add-admin',
            'admin-agents-id',
            'admin-admins-id',
            'admin-clients-id'

          ];
          if (whiteBgPages.includes(newRouteName)) {
            layoutContent.style.background = '#fff';
          } else {
            layoutContent.style.background = 'transparent';
          }
        }
      },
      { immediate: true } // Run immediately on load
    );
    
    return () => {
      // console.log('Nav Items:', props.navItems);
      const verticalNavAttrs = toRef(props, 'verticalNavAttrs')
      const { wrapper: verticalNavWrapper, wrapperProps: verticalNavWrapperProps, ...additionalVerticalNavAttrs } = verticalNavAttrs.value


      // 👉 Vertical nav
      const verticalNav = h(VerticalNav, { isOverlayNavActive: isOverlayNavActive.value, toggleIsOverlayNavActive, navItems: props.navItems, ...additionalVerticalNavAttrs }, {
        'nav-header': () => slots['vertical-nav-header']?.(),
        'before-nav-items': () => slots['before-vertical-nav-items']?.(),
      })


      // 👉 Navbar
      const navbar = h('header', { class: ['layout-navbar', { 'navbar-blur': configStore.isNavbarBlurEnabled }] }, [
        h('div', { class: 'navbar-content-container' }, slots.navbar?.({
          toggleVerticalOverlayNavActive: toggleIsOverlayNavActive,
        })),
      ])


      // 👉 Content area
      const main = h('main', { class: 'layout-page-content' }, h('div', { class: 'page-content-container' }, slots.default?.()))


      // 👉 Footer
      const footer = h('footer', { class: 'layout-footer' }, [
        h('div', { class: 'footer-content-container' }, slots.footer?.()),
      ])


      // 👉 Overlay
      const layoutOverlay = h('div', {
        class: ['layout-overlay', { visible: isLayoutOverlayVisible.value }],
        onClick: () => { isLayoutOverlayVisible.value = !isLayoutOverlayVisible.value },
      })

      return h('div', { class: ['layout-wrapper', ...configStore._layoutClasses] }, [
        verticalNavWrapper ? h(verticalNavWrapper, verticalNavWrapperProps, { default: () => verticalNav }) : verticalNav,
        navbar,
        h('div', { class: 'layout-content-wrapper' }, [
          main,
          footer,
        ]),
        layoutOverlay,
      ])
    }
  },
})
</script>

<style lang="scss">
@use "@configured-variables" as variables;
@use "@layouts/styles/placeholders";
@use "@layouts/styles/mixins";

.layout-wrapper.layout-nav-type-vertical {
  // TODO(v2): Check why we need height in vertical nav & min-height in horizontal nav
  block-size: 100%;

  .layout-content-wrapper {
    display: flex;
    flex-direction: column;
    flex-grow: 1;

    // background: #fff !important;
    min-block-size: 100dvh;
    transition: padding-inline-start 0.2s ease-in-out;
    will-change: padding-inline-start;

    @media screen and (min-width: 1280px) {
      padding-inline-start: variables.$layout-vertical-nav-width;
    }
  }

  .layout-navbar {
    z-index: variables.$layout-vertical-nav-layout-navbar-z-index;

    .navbar-content-container {
      border-radius: 0 !important;
      block-size: variables.$layout-vertical-nav-navbar-height;

      @media (min-width: 1280px) {
        block-size: 100px;
      }

      border-block-end: 1px solid #eee;
      box-shadow: none !important;
    }

    @at-root {
      .layout-wrapper.layout-nav-type-vertical {
        .layout-navbar {
          @if variables.$layout-vertical-nav-navbar-is-contained {
            @include mixins.boxed-content;
          }
          /* stylelint-disable-next-line @stylistic/indentation */
          @else {
            .navbar-content-container {
              @include mixins.boxed-content;
            }
          }
        }
      }
    }
  }

  &.layout-navbar-sticky .layout-navbar {
    @extend %layout-navbar-sticky;

    border-radius: 0 !important;
    max-inline-size: 100% !important;
    padding-inline: 0 !important;
  }

  &.layout-navbar-hidden .layout-navbar {
    @extend %layout-navbar-hidden;
  }

  // 👉 Footer
  .layout-footer {
    @include mixins.boxed-content;

    background: #fff !important;
    border-block-start: 1px solid #eee;
    max-inline-size: 100% !important;
  }

  // 👉 Layout overlay
  .layout-overlay {
    position: fixed;
    z-index: variables.$layout-overlay-z-index;
    background-color: rgb(0 0 0 / 60%);
    cursor: pointer;
    inset: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease-in-out;
    will-change: transform;

    &.visible {
      opacity: 1;
      pointer-events: auto;
    }
  }

  // Adjust right column pl when vertical nav is collapsed
  &.layout-vertical-nav-collapsed .layout-content-wrapper {
    @media screen and (min-width: 1280px) {
      padding-inline-start: variables.$layout-vertical-nav-collapsed-width;
    }
  }

  // 👉 Content height fixed
  &.layout-content-height-fixed {
    .layout-content-wrapper {
      max-block-size: 100dvh;
    }

    .layout-page-content {
      display: flex;
      overflow: hidden;

      .page-content-container {
        inline-size: 100%;

        > :first-child {
          max-block-size: 100%;
          overflow-y: auto;
        }
      }
    }
  }
}

.layout-wrapper.layout-nav-type-vertical.layout-navbar-sticky .layout-navbar {
  position: fixed !important;
}

.layout-wrapper.layout-nav-type-vertical.layout-navbar-sticky .layout-page-content {
    margin-block-start: 40px !important;
}

@media (min-width: 1264px) {
  .layout-wrapper.layout-nav-type-vertical.layout-navbar-sticky .layout-page-content {
    margin-block-start: 100px !important;
  }
}
</style>
