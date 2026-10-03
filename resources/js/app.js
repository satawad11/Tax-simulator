import { initializeNavigation } from './components/navigation.js';
import { initializeAdminConsole } from './admin/console.js';
import { initializeAdminSidebar } from './admin/sidebar.js';
import { initializeAuth, initializeMemberNavigation } from './auth.js';
import { initializePasswordForms } from './password.js';
import { initializeMemberDashboard } from './member-dashboard.js';
import { initializeSimulator } from './simulator-wizard.js';

initializeNavigation();
initializeMemberNavigation();
initializeAuth();
initializePasswordForms();
initializeSimulator();
initializeMemberDashboard();
// Both are no-ops on every page that is not the admin console.
initializeAdminSidebar();
initializeAdminConsole();
