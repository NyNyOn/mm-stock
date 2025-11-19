/**
 * settings.js
 * Handles all CRUD operations for the settings pages
 * (Categories, Locations, Units) using modals and AJAX.
 */

/**
 * Opens the modal for adding or editing a setting item.
 * @param {'category'|'location'|'unit'} type - The type of setting.
 * @param {number|null} id - The ID of the item to edit, or null for adding.
 */
async function openSettingsModal(type, id = null) {
    const modal = document.getElementById(`${type}-modal`);
    const form = modal.querySelector('form');
    const title = modal.querySelector('h3');
    form.reset();
    form.querySelector('[name$="_id"]').value = '';

    if (id) {
        // --- EDIT MODE ---
        title.textContent = `✏️ แก้ไขข้อมูล${getSettingTypeName(type)}`;
        const response = await apiRequest(`get_${type}_details`, { id });
        if (response.success) {
            const data = response.data;
            form.querySelector(`[name="${type}_id"]`).value = data.id;
            form.querySelector(`[name="name"]`).value = data.name;
            if (form.querySelector(`[name="prefix"]`)) {
                form.querySelector(`[name="prefix"]`).value = data.prefix;
            }
        } else {
            showToast(response.message, 'error');
            return;
        }
    } else {
        // --- ADD MODE ---
        title.textContent = `➕ เพิ่ม${getSettingTypeName(type)}ใหม่`;
    }

    showModal(`${type}-modal`);
}

/**
 * Deletes a setting item after a beautiful confirmation.
 * @param {'category'|'location'|'unit'} type - The type of setting.
 * @param {number} id - The ID of the item to delete.
 */
function deleteSetting(type, id) {
    const message = `คุณแน่ใจหรือไม่ว่าต้องการลบ${getSettingTypeName(type)}นี้?`;
    showConfirmationModal(message, async () => {
        const response = await apiRequest(`delete_${type}`, { id });
        if (response.success) {
            showToast(response.message, 'success');
            // Simply reload the page to show the updated list
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(response.message, 'error');
        }
    });
}

/**
 * Helper function to get the Thai name for a setting type.
 * @param {string} type - The setting type (e.g., 'category').
 * @returns {string} - The Thai name (e.g., 'ประเภท').
 */
function getSettingTypeName(type) {
    switch (type) {
        case 'category': return 'ประเภท';
        case 'location': return 'สถานที่';
        case 'unit': return 'หน่วยนับ';
        default: return '';
    }
}
