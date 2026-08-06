<script setup>
import { layoutConfig } from '@layouts';
import { canViewMenuItem } from '@layouts/plugins/casl';
import { useLayoutConfigStore } from '@layouts/stores/config';
import {
  getComputedNavLinkToProp,
  getDynamicI18nProps,
  isNavLinkActive,
} from '@layouts/utils';
import * as feather from 'feather-icons';
import { onMounted } from 'vue';

const props = defineProps({
  item: {
    type: null,
    required: true,
  },
})
// const userRole = 'client';
const configStore = useLayoutConfigStore()
const hideTitleAndBadge = configStore.isVerticalNavMini()

const userData = useCookie('userData').value;

const renderFeatherIcon = (iconName) => {

  if (feather.icons[iconName]) {
    return feather.icons[iconName].toSvg();
  }
  // Return default or fallback icon if necessary
  return feather.icons['alert-circle'].toSvg(); // Default fallback icon
};

onMounted(() => {
  // moveLastItem();
});

// const moveLastItem = () => {
//   // Get the last li element in the list
//   const lastItem = document.querySelector(".layout-vertical-nav ul li:last-child");

//   alert(console.log(lastItem));

//   if (lastItem) {
//     // Move it to the end of the page
//     const endContainer = document.getElementById("last-item-container");
//     endContainer.appendChild(lastItem);
//   }
// }

</script>

<template>
  <li
    v-if="canViewMenuItem(item, userData.role)"
    class="nav-link text-default"
    :class="item.class"
  >
    <Component
      :is="item.to ? 'RouterLink' : 'a'"
      v-bind="getComputedNavLinkToProp(item)"
      :class="{ 'router-link-active router-link-exact-active': isNavLinkActive(item, $router) }"
    >
    <i v-if="item.icon" v-html="renderFeatherIcon(item.icon)" />

      <TransitionGroup name="transition-slide-x">
        <!-- 👉 Title -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-show="!hideTitleAndBadge"
          key="title"
          class="nav-item-title ml-4"
          v-bind="getDynamicI18nProps(item.title, 'span')"
        >
          {{ item.title }}
        </Component>

        <!-- 👉 Badge -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-if="item.badgeContent"
          v-show="!hideTitleAndBadge"
          key="badge"
          class="nav-item-badge"
          :class="item.badgeClass"
          v-bind="getDynamicI18nProps(item.badgeContent, 'span')"
        >
          {{ item.badgeContent }}
        </Component>
      </TransitionGroup>
    </Component>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
  }
}

.layout-nav-type-vertical .layout-vertical-nav .nav-link,
.layout-nav-type-vertical .layout-vertical-nav .nav-group {
  margin-block-end: 0.5rem !important;
  padding-block-end: 0 !important;
}

.last-item {
  position: absolute;
  inline-size: 100%;
  inset-block-end: 0;
}
</style>
