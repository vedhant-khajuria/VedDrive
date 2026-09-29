// Retire the old cached application and its former public credentials.
self.addEventListener('install',()=>self.skipWaiting());
self.addEventListener('activate',event=>event.waitUntil((async()=>{for(const key of await caches.keys()) if(key.startsWith('teledrive-pro-')) await caches.delete(key); await self.registration.unregister(); for(const client of await self.clients.matchAll()) client.navigate(client.url);})()));
