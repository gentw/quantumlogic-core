import { accessState } from '@/@core/stores/access';
const router = useRouter();
// Helper to fetch client profile and handle 403
const fetchClientProfile = async () => {
    try {
      const res = await $api('https://api-ds.bitemybytes.com/api/v1/client/profile', { method: 'POST' });
      const { user, subscription } = res.data;
  
      // Store locally for reactivity
      localStorage.setItem('user', JSON.stringify(user));
      localStorage.setItem('subscription', JSON.stringify(subscription));
  
      return subscription;
    } catch (err) {
      if (err.response && err.response.status === 403) {
      
        // No active subscription → redirect to pricing
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
        const redirectUri = useCookie('redirect_uri').value;
        console.log(`Navigating to: ${to.name}, from: ${from.name}`);

        
        if(useCookie('userData').value) {
            const userRole = useCookie('userData').value.role;
            // Define role-based access rules
            const roleAccess = {
                admin: ['/admin'], // Admin can access all
                client: ['/client'], // Client can only access client routes
                agent: ['/agent'], // Agent can only access agent routes
            };
            const isAuthorized = roleAccess[userRole].some(route => to.path.startsWith(route));

            if(isAuthorized) {
                next();
            } else {
                next({name: userRole});
            }
        }

        
        
    
        if (to.name === 'checkpoint' || to.name === 'add-user-email') {
            if (!isLoggedIn && isOtp) {
                // Allow access to OTP page if not logged in and isOtp is true
                next()                                
            } else {
                // Redirect to login if user is fully authenticated or isOtp is false
                next({ name: 'login' });
            }
        } else {
            // if (!isLoggedIn && isOtp) {
            //     next('checkpoint');
            //     // alert(1);
            //     // alert('checkpoint')
            //     // if(redirectUri && redirectUri !== to.path) {
            //     //     next(redirectUri);
            //     //     alert(redirectUri)                    
            //     // } 
            // } else {
            // Check if the target route is in the protected routes list
            if (protectedRoutes.includes(to.name) && !isLoggedIn) {
                next({ name: 'login' }); // Redirect to login if not logged in
            } else if (to.name === 'login' && isLoggedIn) {
                next({ name: 'second-page' }); // Redirect to home if already logged in
            } else {
                next(); // Allow the navigation
            }

             
            // }
        }
    

        // 🔐 Check subscription
        if(useCookie('userData').value.role == 'client') {
            // Allow everything except invoice pages
            const notRequiresSub = to.path.startsWith('/client/invoice')

            if (notRequiresSub) {
                return next() // free access
            }

            try {
                const res = await $api('https://api-ds.bitemybytes.com/api/v1/client/profile',  { method: 'POST' });
                const subscription = res.data.subscription;
        
                if (!subscription || subscription.status !== 'active') {
                    accessState.showPage = !!res.data.active;
                return next({ name: 'client-pricing' });
                
                }
        
                localStorage.setItem('subscription', JSON.stringify(subscription));
                next();
            } catch (err) {
                if (err.response?.status === 403) {
                    accessState.showPage = false;
                    return router.push('/client/pricing');
                
                }
        
                accessState.checked = true;
                return next(false);
            }
        }
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
