<script setup>
// import avatar1 from '@images/avatars/avatar-1.png';
import { appFeatures } from '@/utils/features';

const userData = computed(() => useCookie('userData').value ?? {});
const avatar1 = computed(() => userData.value.img);

/**
 * End the session. Revoking the token server-side is best-effort — an expired
 * or already-revoked token answers 401, and that must not leave the user signed
 * in on this device, so the local teardown runs in `finally`.
 *
 * Leaves via a full document navigation rather than the router: that discards
 * Pinia state and the open Pusher subscription along with the cookies, and
 * `replace` keeps the dashboard out of the back-button history.
 */
const logoutUser = async () => {
  try {
    await $api('/v1/logout', { method: 'POST' });
  } catch {
    // Nothing to recover: the session ends locally either way.
  } finally {
    clearSession();
    window.location.replace('/login');
  }
};
</script>

<template>
  <div class="d-flex align-items-center">
    <!-- Wrapper to make both avatar and name clickable for the menu -->
    <div class="d-flex align-items-center cursor-pointer" style="gap: 10px;">
      <!-- Avatar with Badge -->
      <VBadge
        dot
        location="bottom right"
        offset-x="3"
        offset-y="3"
        bordered
        color="success"
        class="cursor-pointer"
      >
        <VAvatar color="primary" variant="tonal">
          <VImg :src="avatar1" />
        </VAvatar>
      </VBadge>
      
      <!-- User name -->
      <div class="d-none d-sm-block" style="margin-block-start: 8px;">
        <p class="text-default">{{ userData.name }}</p>
      </div>

      <!-- Menu Triggered by Avatar and Name -->
      <VMenu activator="parent" width="230" location="bottom end" offset="14px">
        <VList>
          <VListItem class="d-sm-none d-block">
            <template #prepend>
              <VListItemAction start>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                >
                  <VAvatar
                    color="primary"
                    variant="tonal"
                  >
                  </VAvatar>
                </VBadge>
              </VListItemAction>
            </template>
            <VListItemTitle class="font-weight-semibold">
              {{ userData.name }}
            </VListItemTitle>
            <VListItemSubtitle>{{ userData.role }}</VListItemSubtitle>
          </VListItem>
          <VDivider class="my-2 d-sm-none d-block" />
          <!-- Profile -->
          <VListItem link :to="`/${userData.role}/account`">
            <template #prepend>
              <VIcon class="me-2" icon="tabler-user" size="22" />
            </template>
            <VListItemTitle>Profile</VListItemTitle>
          </VListItem>

          <!-- Plans & Billing — retired subscription-plans module. The route guard
               blocks client-plans-billing while the flag is off, so showing the item
               would be a dead link. Kept behind the flag rather than deleted. -->
          <VListItem
            v-if="appFeatures.subscriptionPlans"
            link
            :to="'/client/plans-billing'"
          >
            <template #prepend>
              <VIcon class="me-2" icon="tabler-file-invoice" size="22" />
            </template>
            <VListItemTitle>Plans & Billing</VListItemTitle>
          </VListItem>

          <!-- Logout -->
          <VListItem @click="logoutUser">
            <template #prepend>
              <VIcon class="me-2" icon="tabler-logout" size="22" />
            </template>
            <VListItemTitle>Logout</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
    </div>
  </div>
</template>
