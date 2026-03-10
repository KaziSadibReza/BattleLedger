import React, { useState } from "react";
import Dashboard from "./pages/Dashboard";
import Tournaments from "./pages/Tournaments";
import LiveTournaments from "./pages/LiveTournaments";
import FinishedTournaments from "./pages/FinishedTournaments";
import Rules from "./pages/Rules";
import Settings from "./pages/Settings";
import AuthSettingsPage from "./pages/AuthSettingsPage";
import FormCustomization from "./pages/FormCustomization";
import FrontendAppearance from "./pages/FrontendAppearance";
import EmailTemplates from "./pages/EmailTemplates";
import Wallets from "./pages/Wallets";
import Notifications from "./components/Notifications";

// Navigation Types
type Tab = "dashboard" | "tournaments" | "live" | "finished" | "rules" | "wallets" | "settings" | "auth" | "form-design" | "frontend-appearance" | "email-templates";

function App() {
  const [activeTab, setActiveTab] = useState<Tab>("dashboard");
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(false);

  // Listen to hash changes for Quick Action navigation
  React.useEffect(() => {
    const handleHashChange = () => {
      const hash = window.location.hash.replace("#", "");
      if (
        hash === "tournaments" ||
        hash === "live" ||
        hash === "finished" ||
        hash === "rules" ||
        hash === "wallets" ||
        hash === "settings" ||
        hash === "auth" ||
        hash === "form-design" ||
        hash === "frontend-appearance" ||
        hash === "email-templates"
      ) {
        setActiveTab(hash as Tab);
      }
    };
    window.addEventListener("hashchange", handleHashChange);
    return () => window.removeEventListener("hashchange", handleHashChange);
  }, []);

  const handleNavigate = (tab: string) => {
    setActiveTab(tab as Tab);
  };

  const renderContent = () => {
    switch (activeTab) {
      case "dashboard":
        return <Dashboard onNavigate={handleNavigate} />;
      case "tournaments":
        return <Tournaments />;
      case "live":
        return <LiveTournaments />;
      case "finished":
        return <FinishedTournaments />;
      case "rules":
        return <Rules />;
      case "wallets":
        return <Wallets />;
      case "auth":
        return <AuthSettingsPage />;
      case "form-design":
        return <FormCustomization />;
      case "frontend-appearance":
        return <FrontendAppearance />;
      case "email-templates":
        return <EmailTemplates />;
      case "settings":
        return <Settings />;
      default:
        return <Dashboard onNavigate={handleNavigate} />;
    }
  };

  return (
    <div className="battle-ledger-app">
      {/* Sidebar */}
      <aside className={`bl-sidebar ${isSidebarCollapsed ? "collapsed" : ""}`}>
        <div className="bl-brand">
          <span className="dashicons dashicons-games"></span>
          {!isSidebarCollapsed && <h2>BattleLedger</h2>}
        </div>
        <nav className="bl-nav">
          <button
            className={activeTab === "dashboard" ? "active" : ""}
            onClick={() => setActiveTab("dashboard")}
            title="Dashboard"
          >
            <span className="dashicons dashicons-chart-area"></span>
            {!isSidebarCollapsed && <span>Dashboard</span>}
          </button>
          <button
            className={activeTab === "tournaments" ? "active" : ""}
            onClick={() => setActiveTab("tournaments")}
            title="Tournaments"
          >
            <span className="dashicons dashicons-flag"></span>
            {!isSidebarCollapsed && <span>Tournaments</span>}
          </button>
          <button
            className={activeTab === "live" ? "active" : ""}
            onClick={() => setActiveTab("live")}
            title="Live Tournaments"
          >
            <span className="dashicons dashicons-controls-play"></span>
            {!isSidebarCollapsed && <span>Live</span>}
          </button>
          <button
            className={activeTab === "finished" ? "active" : ""}
            onClick={() => setActiveTab("finished")}
            title="Finished Tournaments"
          >
            <span className="dashicons dashicons-yes-alt"></span>
            {!isSidebarCollapsed && <span>Finished</span>}
          </button>
          <button
            className={activeTab === "rules" ? "active" : ""}
            onClick={() => setActiveTab("rules")}
            title="Rules Engine"
          >
            <span className="dashicons dashicons-shield"></span>
            {!isSidebarCollapsed && <span>Rules Engine</span>}
          </button>
          <button
            className={activeTab === "wallets" ? "active" : ""}
            onClick={() => setActiveTab("wallets")}
            title="Wallets"
          >
            <span className="dashicons dashicons-money-alt"></span>
            {!isSidebarCollapsed && <span>Wallets</span>}
          </button>
          <button
            className={activeTab === "auth" ? "active" : ""}
            onClick={() => setActiveTab("auth")}
            title="Authentication"
          >
            <span className="dashicons dashicons-shield"></span>
            {!isSidebarCollapsed && <span>Authentication</span>}
          </button>
          <button
            className={activeTab === "form-design" ? "active" : ""}
            onClick={() => setActiveTab("form-design")}
            title="Form Design"
          >
            <span className="dashicons dashicons-art"></span>
            {!isSidebarCollapsed && <span>Form Design</span>}
          </button>
          <button
            className={activeTab === "frontend-appearance" ? "active" : ""}
            onClick={() => setActiveTab("frontend-appearance")}
            title="Frontend Appearance"
          >
            <span className="dashicons dashicons-admin-appearance"></span>
            {!isSidebarCollapsed && <span>Appearance</span>}
          </button>
          <button
            className={activeTab === "email-templates" ? "active" : ""}
            onClick={() => setActiveTab("email-templates")}
            title="Email Templates"
          >
            <span className="dashicons dashicons-email"></span>
            {!isSidebarCollapsed && <span>Email Templates</span>}
          </button>
          <button
            className={activeTab === "settings" ? "active" : ""}
            onClick={() => setActiveTab("settings")}
            title="Settings"
          >
            <span className="dashicons dashicons-admin-settings"></span>
            {!isSidebarCollapsed && <span>Settings</span>}
          </button>
        </nav>
        <button
          className="bl-sidebar-toggle"
          onClick={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
          title={isSidebarCollapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          <span
            className={`dashicons ${
              isSidebarCollapsed
                ? "dashicons-arrow-right-alt2"
                : "dashicons-arrow-left-alt2"
            }`}
          ></span>
        </button>
      </aside>

      {/* Main Content */}
      <main className="bl-main">
        <header className="bl-header">
          <h3>{activeTab.charAt(0).toUpperCase() + activeTab.slice(1)}</h3>
          <div className="bl-header-actions">
            <Notifications />
          </div>
        </header>
        <div className="bl-content-wrapper">{renderContent()}</div>
      </main>
    </div>
  );
}

export default App;
