import { accessState } from '@/@core/stores/access';
import { isDisabledModuleRoute } from '@/utils/features';
const router = useRouter();
const ENTITLED_STATES = ['active', 'trial_active'];

const isEntitled = subscription => {
  if (!subscription) return false;
  if (subscription.state) {
    if (!ENTITLED_STATES.includes(subscription.state)) return false;
  } else if (!['active', 'trial'].includes(subscription.status)) {
    return false;
  }
  if (subscription.end_date && new Date(subscription.end_date) <= new Date()) {
    return false;
  }
  return true;
};

const fetchClientProfile = async () => {
  try {
    const res = await $api('/v1/client/profile', { method: 'POST' });
    const { user, subscription } = res;

    localStorage.setItem('user', JSON.stringify(user));
    localStorage.setItem('subscription', JSON.stringify(subscription));

    return subscription;
  } catch (err) {
    if (err.response && err.response.status === 403) {
      window.location = '/client/pricing';
      return null;
    }

    console.error('Error fetching client profile:', err);
    return null;
  }
};

export const setupGuards = router => {

    const protectedRoutes = [
        'second-page', 'root', 'client', 'agent', 'admin'
    ]; // Add route names that require authentication

    // const clientRoutes = protectedRoutes.filter(route => route.startsWith('client-'));
    const clientRoutes = router.getRoutes()
    .filter((route) => route.name?.toString().startsWith('client-'))
    .map((route) => route.name?.toString());
  
    protectedRoutes.push(...clientRoutes);

    

    router.beforeEach( async(to, from, next) => {
        
        const isLoggedIn = !!(useCookie('userData').value && useCookie('accessToken').value);
        const isOtp = !!(useCookie('isOtp').value);
        const userRole = useCookie('userData').value?.role;

        // Dormant modules keep their pages on disk, so the file-based router still
        // registers them. This is what actually makes them unreachable — and it must
        // not depend on being logged in, or anonymous visitors still render the page.
        if (isDisabledModuleRoute(to.name)) {
            return next(userRole ? { name: userRole } : { name: 'login' });
        }

        // Role-based path enforcement: if the user is logged in but trying to
        // visit a path outside their role's prefix, bounce them to their root.
        if (userRole) {
            const roleAccess = {
                admin: ['/admin'],
                client: ['/client'],
                agent: ['/agent'],
            };
            const isAuthorized = roleAccess[userRole]?.some(route => to.path.startsWith(route));

            if (!isAuthorized && roleAccess[userRole]) {
                return next({ name: userRole });
            }
            // authorized → fall through to OTP / auth / subscription checks
        }

        if (to.name === 'checkpoint' || to.name === 'add-user-email') {
            if (!isLoggedIn && isOtp) {
                return next();
            }
            return next({ name: 'login' });
        }

        if (protectedRoutes.includes(to.name) && !isLoggedIn) {
            return next({ name: 'login' });
        }
        if (to.name === 'login' && isLoggedIn) {
            return next({ name: 'second-page' });
        }

        // 🔐 Subscription gate (clients only). Pricing & invoice pages are unguarded.
        if (userRole === 'client') {
            const notRequiresSub = to.path.startsWith('/client/invoice') || to.path.startsWith('/client/pricing');

            if (notRequiresSub) {
                accessState.showPage = true;
                return next();
            }

            try {
                const res = await $api('/v1/client/profile', { method: 'POST' });
                const subscription = res.subscription;

                if (!isEntitled(subscription)) {
                    accessState.showPage = false;
                    return next({ name: 'client-pricing' });
                }

                accessState.showPage = true;
                localStorage.setItem('subscription', JSON.stringify(subscription));
                return next();
            } catch (err) {
                if (err.response?.status === 403) {
                    accessState.showPage = false;
                    return next({ name: 'client-pricing' });
                }

                accessState.checked = true;
                return next(false);
            }
        }

        return next();
    });
  // Docs: https://router.vuejs.org/guide/advanced/navigation-guards.html#global-before-guards
//   router.beforeEach(to => {
//     /*
//          * If it's a public route, continue navigation. This kind of pages are allowed to visited by login & non-login users. Basically, without any restrictions.
//          * Examples of public routes are, 404, under maintenance, etc.
//          */
//     if (to.meta.public)
//       return

//     /**
//          * Check if user is logged in by checking if token & user data exists in local storage
//          * Feel free to update this logic to suit your needs
//          */
//     const isLoggedIn = !!(useCookie('userData').value && useCookie('accessToken').value)

//     /*
//           If user is logged in and is trying to access login like page, redirect to home
//           else allow visiting the page
//           (WARN: Don't allow executing further by return statement because next code will check for permissions)
//          */
//     // if (to.meta.unauthenticatedOnly) {
//         return isLoggedIn
//         ? { name: 'second-page' }
//         : {
//             name: 'login',
//             query: {
//                 ...to.query,
//                 to: to.fullPath !== '/' ? to.path : undefined,
//             },
//         }
//     // }
//     // if (!canNavigate(to) && to.matched.length) {
//     //   /* eslint-disable indent */
//     //         return isLoggedIn
//     //             ? { name: 'second-page' }
//     //             : {
//     //                 name: 'login',
//     //                 query: {
//     //                     ...to.query,
//     //                     to: to.fullPath !== '/' ? to.path : undefined,
//     //                 },
//     //             }
//     //         /* eslint-enable indent */
//     // }
//   })
}
