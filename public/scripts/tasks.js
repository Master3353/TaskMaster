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

