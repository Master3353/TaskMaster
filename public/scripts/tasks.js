const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const taskCards = document.querySelectorAll('.task-card');

function filterTasks() {

    const searchTerm = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value;

    taskCards.forEach(card => {
        const taskName = card.querySelector('h3').textContent.toLowerCase();
        const taskDesc = card.querySelector('.task-description').textContent.toLowerCase();
        const taskStatus = card.dataset.status;

        const matchesSearch = taskName.includes(searchTerm) || taskDesc.includes(searchTerm);
        const matchesStatus = !statusValue || taskStatus === statusValue;

        card.style.display = (matchesSearch && matchesStatus) ? 'block' : 'none';
    });
}
searchInput.addEventListener('input', filterTasks);
statusFilter.addEventListener('change', filterTasks);

const hamburger = document.getElementById('hamburger');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('active');
}

function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
}

hamburger.addEventListener('click', () => {
    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        openSidebar();
    }
});

overlay.addEventListener('click', closeSidebar);

sidebar.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeSidebar);
});

