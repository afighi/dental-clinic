/**
 * Menu Toggle JavaScript
 * Handles responsive menu functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            menuToggle.classList.toggle('active');
            
            // Toggle aria-expanded for accessibility
            const expanded = menuToggle.getAttribute('aria-expanded') === 'true' || false;
            menuToggle.setAttribute('aria-expanded', !expanded);
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (mainNav && mainNav.classList.contains('active')) {
            if (!mainNav.contains(event.target) && !menuToggle.contains(event.target)) {
                mainNav.classList.remove('active');
                menuToggle.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });
    
    // Handle dropdown menus for desktop
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const parent = this.parentElement;
            const dropdown = parent.querySelector('.dropdown-menu');
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown.active').forEach(function(activeDropdown) {
                if (activeDropdown !== parent) {
                    activeDropdown.classList.remove('active');
                    activeDropdown.querySelector('.dropdown-menu').style.display = 'none';
                    activeDropdown.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
                }
            });
            
            // Toggle current dropdown
            parent.classList.toggle('active');
            
            if (parent.classList.contains('active')) {
                dropdown.style.display = 'block';
                this.setAttribute('aria-expanded', 'true');
            } else {
                dropdown.style.display = 'none';
                this.setAttribute('aria-expanded', 'false');
            }
        });
    });
    
    // Add CSS styles for dashboard sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.account-sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            document.querySelector('.account-content').classList.toggle('expanded');
            
            // Update toggle icon
            const icon = sidebarToggle.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-angle-left');
                icon.classList.add('fa-angle-right');
                sidebarToggle.setAttribute('title', 'Expand Sidebar');
            } else {
                icon.classList.remove('fa-angle-right');
                icon.classList.add('fa-angle-left');
                sidebarToggle.setAttribute('title', 'Collapse Sidebar');
            }
        });
    }
    
    // Add active class to current menu item based on URL
    const currentLocation = window.location.pathname;
    const menuItems = document.querySelectorAll('.main-nav a, .account-menu a');
    
    menuItems.forEach(function(item) {
        const itemHref = item.getAttribute('href');
        
        if (currentLocation === itemHref || currentLocation.indexOf(itemHref) === 0) {
            item.classList.add('active');
            
            // If in a dropdown, also mark parent as active
            const dropdownParent = item.closest('.dropdown');
            if (dropdownParent) {
                dropdownParent.classList.add('active');
            }
        }
    });
});