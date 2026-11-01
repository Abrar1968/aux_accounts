import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Public Pusher config comes from VITE_ env (key/cluster are public; the secret stays server-side).
window.Pusher = Pusher;

const key = import.meta.env.VITE_PUSHER_APP_KEY;

window.Echo = key
    ? new Echo({
          broadcaster: 'pusher',
          key,
          cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
          forceTLS: true,
      })
    : null;

export default window.Echo;
