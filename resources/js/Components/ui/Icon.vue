<script setup>
// Inline 24px stroke icons (no icon library dependency).
defineProps({ name: { type: String, required: true }, size: { type: [Number, String], default: 18 } });
const paths = {
    home: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    shield: 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z M9 12l2 2 4-4',
    briefcase: 'M4 7h16v12H4z M9 7V5h6v2 M4 12h16',
    check: 'M20 6 9 17l-5-5',
    inbox: 'M4 13h4l2 3h4l2-3h4 M5 5h14l1 8v6H4v-6z',
    clock: 'M12 7v5l3 2 M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
    megaphone: 'M4 10v4h3l7 4V6L7 10z M18 9a4 4 0 0 1 0 6',
    link: 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1 M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6 M9 12h6',
    users: 'M16 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M22 20v-1a4 4 0 0 0-3-3.9 M16 3.1a4 4 0 0 1 0 7.8',
    wallet: 'M3 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M3 7l12-4v4 M17 13h.01',
    gift: 'M4 11h16v9H4z M3 7h18v4H3z M12 7v13 M12 7c-2-4-6-3-5 0 M12 7c2-4 6-3 5 0',
    box: 'M3 7l9-4 9 4v10l-9 4-9-4z M3 7l9 4 9-4 M12 11v10',
    scale: 'M12 3v18 M5 7h14 M5 7l-3 7a3 3 0 0 0 6 0z M19 7l-3 7a3 3 0 0 0 6 0z',
    pie: 'M12 3v9h9 M21 12a9 9 0 1 1-9-9',
    doc: 'M6 3h9l4 4v14H6z M14 3v5h5 M9 13h6 M9 17h6',
    sun: 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z M12 1v2 M12 21v2 M4.2 4.2l1.4 1.4 M18.4 18.4l1.4 1.4 M1 12h2 M21 12h2',
    phone: 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2',
    columns: 'M4 4h5v16H4z M10 4h5v10h-5z M16 4h4v13h-4z',
    bell: 'M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9 M10 21a2 2 0 0 0 4 0',
    calendar: 'M4 5h16v16H4z M16 3v4 M8 3v4 M4 10h16',
    chart: 'M4 20V10 M10 20V4 M16 20v-7 M22 20H2',
    cog: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-1.9.2 7 7 0 0 1-1.6.9 1.7 1.7 0 0 0-1.1 1.6H9.6a1.7 1.7 0 0 0-1.1-1.6 7 7 0 0 1-1.6-.9 1.7 1.7 0 0 0-1.9-.2l-.2.1-2-3.4.1-.1a1.7 1.7 0 0 0 .3-1.8 7 7 0 0 1 0-6 1.7 1.7 0 0 0-.3-1.8l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 1.9-.2 7 7 0 0 1 1.6-.9A1.7 1.7 0 0 0 9.6 1h4.8a1.7 1.7 0 0 0 1.1 1.6 7 7 0 0 1 1.6.9 1.7 1.7 0 0 0 1.9.2l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.8 7 7 0 0 1 0 6z',
    bolt: 'M13 2 4 14h7l-1 8 9-12h-7z',
    menu: 'M4 6h16 M4 12h16 M4 18h16',
    x: 'M6 6l12 12 M18 6 6 18',
    search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M21 21l-4.3-4.3',
    moon: 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
    info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 16v-4 M12 8h.01',
    alert: 'M12 3 2 20h20z M12 9v5 M12 17h.01',
};
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path :d="paths[name] ?? paths.info" />
    </svg>
</template>
