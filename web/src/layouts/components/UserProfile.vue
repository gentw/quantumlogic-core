<script setup>
// import avatar1 from '@images/avatars/avatar-1.png';

const avatar1 = useCookie('userData').value.img;

const route = useRoute();
const router = useRouter();
const logoutUser = async () => {
  try {
    const res = await $api('https://api-ds.bitemybytes.com/api/v1/logout', {
      method: 'POST',
    });

    // const { token, user } = res;

    useCookie('isOtp').value = false;
    useCookie('accessToken').value = false;
    useCookie('phoneNo').value = false;
    useCookie('userData').value = false;


    useCookie('chatClientId').value = false
    useCookie('chatChatId').value = false
    location.reload(); 
  } catch (err) {
    console.error(err);
  }
};


onBeforeMount(() => {
  // clientData();
  
})
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
        <p class="text-default">{{ useCookie('userData').value.name }}</p>
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
              {{ useCookie('userData').value.name }}
            </VListItemTitle>
            <VListItemSubtitle>{{ useCookie('userData').value.role }}</VListItemSubtitle>
          </VListItem>
          <VDivider class="my-2 d-sm-none d-block" />
          <!-- Profile -->
          <VListItem link :to="'/'+useCookie('userData').value.role + '/account'">
            <template #prepend>
              <VIcon class="me-2" icon="tabler-user" size="22" />
            </template>
            <VListItemTitle>Profile</VListItemTitle>
          </VListItem>

          <!-- Settings -->
          <VListItem link :to="'/client/plans-billing'">
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
