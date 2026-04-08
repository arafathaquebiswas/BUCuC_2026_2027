// Dashboard Fixes - Scrolling and Category Updates
// This script fixes the scrolling issue in the Total Members section after Cancel button clicks
// and updates the category lists to include the new 8 categories

(function() {
    'use strict';

    // New categories to replace the existing 5 categories
    const NEW_CATEGORIES = [
        'Admin',
        'PR-Public Relations and Editorial', 
        'HR-Human Resources',
        'EM-Event Management and Logistics',
        'Creative',
        'Performance',
        'R&D-Research and Development',
        'MIAP-Marketing IT Archive & Photography'
    ];

    // Initialize fixes when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Initializing dashboard fixes...');
        
        // Fix modal scrolling issues
        fixModalScrollingIssues();
        
        // Update category lists
        updateCategoryLists();
        
        // Update category display in stats card
        updateStatsCardCategories();
        
        console.log('Dashboard fixes applied successfully');
    });

    /**
     * Fix scrolling issues after modal Cancel button clicks
     */
    function fixModalScrollingIssues() {
        console.log('Setting up modal scrolling fixes...');

        // Get all modals in the dashboard
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(modal => {
            // Handle when modal is hidden (including Cancel button clicks)
            modal.addEventListener('hidden.bs.modal', function(event) {
                console.log('Modal hidden, preventing unwanted scrolling...');
                
                // Ensure body scrolling is restored properly
                setTimeout(() => {
                    // Remove any residual modal-open classes
                    document.body.classList.remove('modal-open');
                    
                    // Reset body styles that might cause scrolling issues
                    document.body.style.paddingRight = '';
                    document.body.style.overflow = '';
                    
                    // Remove any backdrop elements that might interfere
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    
                    // Prevent any scrolling to top behavior
                    event.preventDefault();
                    
                    // Maintain current scroll position
                    const currentScrollY = window.scrollY;
                    window.scrollTo(0, currentScrollY);
                    
                }, 50);
            });

            // Handle Cancel button clicks specifically
            const cancelButtons = modal.querySelectorAll('[data-bs-dismiss="modal"]');
            cancelButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    console.log('Cancel button clicked, preventing scroll jump...');
                    
                    // Store current scroll position
                    const scrollPosition = window.scrollY;
                    
                    // Close the modal
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    // Restore scroll position after modal closes
                    setTimeout(() => {
                        window.scrollTo(0, scrollPosition);
                    }, 100);
                    
                    event.preventDefault();
                });
            });
        });

        // Fix the dashboard update modal specifically
        const dashboardModal = document.getElementById('dashboardUpdateModal');
        if (dashboardModal) {
            // Override the closeModal function to prevent scrolling
            if (window.closeModal) {
                const originalCloseModal = window.closeModal;
                window.closeModal = function() {
                    const scrollPosition = window.scrollY;
                    originalCloseModal();
                    setTimeout(() => {
                        window.scrollTo(0, scrollPosition);
                    }, 50);
                };
            }
        }

        console.log('Modal scrolling fixes applied');
    }

    /**
     * Update all category select lists with the new 8 categories
     */
    function updateCategoryLists() {
        console.log('Updating category lists...');

        // Find all category select elements
        const categorySelects = document.querySelectorAll('#memberCategory, #applicationCategory, #genderCategory');
        
        categorySelects.forEach(select => {
            // Clear existing options
            select.innerHTML = '';
            
            // Add new category options
            NEW_CATEGORIES.forEach(category => {
                const option = document.createElement('option');
                option.value = category;
                option.textContent = category;
                select.appendChild(option);
            });
            
            console.log(`Updated category select: ${select.id}`);
        });

        console.log('Category lists updated with 8 new categories');
    }

    /**
     * Update the stats card to show the new categories
     */
    function updateStatsCardCategories() {
        console.log('Updating stats card categories...');

        // Update the categories stat card
        const categoriesCard = document.querySelector('[data-stat="categories"]');
        if (categoriesCard) {
            // Update the number
            const statNumber = categoriesCard.querySelector('.stat-number');
            if (statNumber) {
                statNumber.textContent = '8';
            }

            // Update the category list display
            const statChange = categoriesCard.querySelector('.stat-change');
            if (statChange) {
                // Create a shortened display of categories
                const shortCategories = [
                    'Admin', 'PR-Editorial', 'HR', 'Event Mgmt', 
                    'Creative', 'Performance', 'R&D', 'MIAP'
                ];
                
                statChange.innerHTML = `
                    <i class="fas fa-palette"></i>
                    ${shortCategories.join(', ')}
                `;
            }
        }

        console.log('Stats card categories updated');
    }

    /**
     * Utility function to prevent body scroll during modal operations
     */
    function preventBodyScroll() {
        const scrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollY}px`;
        document.body.style.width = '100%';
        return scrollY;
    }

    /**
     * Utility function to restore body scroll after modal operations
     */
    function restoreBodyScroll(scrollY) {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, scrollY);
    }

    // Make functions available globally for debugging
    window.dashboardFixes = {
        fixModalScrollingIssues,
        updateCategoryLists,
        updateStatsCardCategories,
        NEW_CATEGORIES,
        preventBodyScroll,
        restoreBodyScroll
    };

    console.log('Dashboard fixes script loaded. Debug functions available at window.dashboardFixes');

})();
