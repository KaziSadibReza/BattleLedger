/**
 * Frontend Dashboard Module
 */

export * from './components';
export { initializeDashboardContainers } from './frontend-dashboard';

// Re-export for convenience
import { initializeDashboardContainers } from './frontend-dashboard';
export default initializeDashboardContainers;
