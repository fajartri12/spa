import axios from 'axios';
import { createIcons } from 'lucide';
import lucideIcons from './icons';
import { initDashboardCharts } from './charts';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';

const renderIcons = () => {
    createIcons({ icons: lucideIcons, attrs: { 'stroke-width': 1.8 } });
};

const refreshCharts = () => {
    initDashboardCharts();
};

document.addEventListener('DOMContentLoaded', () => {
    renderIcons();
    refreshCharts();
});

document.addEventListener('livewire:navigated', () => {
    renderIcons();
    refreshCharts();
});

document.addEventListener('livewire:init', () => {
    if (!window.Livewire) {
        return;
    }

    window.Livewire.hook('morph.updated', () => {
        renderIcons();
        refreshCharts();
    });
});
