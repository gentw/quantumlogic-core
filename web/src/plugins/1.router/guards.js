import { accessState } from '@/@core/stores/access';
import { appFeatures, isDisabledModuleRoute } from '@/utils/features';
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

/**
 * Every route behind the login. Derived from the route name rather than a
 * hand-kept list: the previous version only auto-expanded `client-*`, so
 * `admin-*` and `agent-*` pages mounted for signed-out visitors and failed
 * later, one 401 at a time, instead of redirecting to the login page.
 *
 * @param {string|symbol|null|undefined} routeName - `to.name` from a navigation.
 * @returns {boolean}
 */
const requiresAuth = routeName => {
    const name = routeName?.toString() ?? '';

    if (!name) return false;

    const roleRoots = ['client', 'agent', 'admin', 'root', 'second-page'];

    return roleRoots.includes(name)
        || ['client-', 'agent-', 'admin-'].some(prefix => name.startsWith(prefix));
};

export const setupGuards = router => {

    router.beforeEach( async(to, from, next) => {

        const isLoggedIn = hasSession();
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

        if (requiresAuth(to.name) && !isLoggedIn) {
            return next({ name: 'login' });
        }
        if (to.name === 'login' && isLoggedIn) {
            // Their own dashboard, not `second-page` — that is the tickets
            // placeholder, and it is hidden while the tickets flag is off.
            return next({ name: userRole ?? 'root' });
        }

        // 🔐 Subscription gate (clients only). Pricing & invoice pages are unguarded.
        // Retired module: with subscription plans off, clients are never locked out
        // of the portal for lacking a plan — accessState keeps its default and the
        // navigation falls through to the final next() below.
        if (userRole === 'client' && appFeatures.subscriptionPlans) {
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
