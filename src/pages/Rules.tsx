import React, { useState, useEffect, useCallback } from "react";
import apiFetch from "@wordpress/api-fetch";
import eventBus from "../lib/eventBus";
import { invalidateRulesCache } from "../lib/useGameRules";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
  horizontalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
  Gamepad2,
  Plus,
  Trash2,
  Edit3,
  Eye,
  EyeOff,
  Save,
  X,
  GripVertical,
  Map,
  Users,
  UserPlus,
  Link2,
  ChevronDown,
  ChevronUp,
  Settings2,
  Copy,
  ArrowLeft,
  Image,
  Upload,
  ToggleLeft,
  FileText,
  Hash,
  Type,
  AtSign,
  Loader2,
} from "lucide-react";
import Toast from "../components/Toast";
import Dropdown from "../components/Dropdown";

// Declare WordPress media types
declare const wp: {
  media: (options: any) => {
    on: (event: string, callback: () => void) => any;
    open: () => void;
    state: () => { get: (key: string) => { toJSON: () => { url: string; id: number }[] } };
  };
};

// Types
interface MapConfig {
  id: string;
  name: string;
  image?: string;
}

// Player Identity Field - Dynamic field configuration
interface PlayerFieldConfig {
  id: string;
  name: string;
  type: "text" | "number" | "email";
  placeholder: string;
  required: boolean;
  validation?: string; // regex pattern
}

// Game Settings - Toggleable game rules
interface GameSettingConfig {
  id: string;
  name: string;
  enabled: boolean;
}

// Team Mode with players per team for validation
interface TeamModeConfig {
  id: string;
  name: string;
  playersPerTeam: number;
}

interface GameModeConfig {
  id: string;
  name: string;
  allowedMaps: string[];
  allowedTeamModes: string[]; // references TeamModeConfig.id
  allowedPlayerCounts: number[];
  // Game-specific settings per mode
  settings: GameSettingConfig[];
}

interface GameRule {
  id: number | string; // number from DB, string for unsaved new rule
  gameName: string;
  gameIcon: string;
  gameImage?: string;
  gameModes: GameModeConfig[];
  allMaps: MapConfig[];
  allTeamModes: TeamModeConfig[];
  allPlayerCounts: number[];
  isActive: boolean;
  playerFields: PlayerFieldConfig[];
  availableSettings: GameSettingConfig[];
}

// ── API helpers ──────────────────────────────────────────────────────

interface ApiRule {
  id: number;
  game_name: string;
  slug: string;
  game_icon: string;
  game_image: string;
  is_active: boolean;
  sort_order: number;
  all_maps: MapConfig[];
  all_team_modes: TeamModeConfig[];
  all_player_counts: number[];
  player_fields: PlayerFieldConfig[];
  available_settings: GameSettingConfig[];
  game_modes: GameModeConfig[];
}

/** Map API snake_case → frontend camelCase */
function apiToLocal(r: ApiRule): GameRule {
  return {
    id: r.id,
    gameName: r.game_name,
    gameIcon: r.game_icon || "",
    gameImage: r.game_image || "",
    isActive: r.is_active,
    allMaps: r.all_maps || [],
    allTeamModes: r.all_team_modes || [],
    allPlayerCounts: r.all_player_counts || [],
    playerFields: r.player_fields || [],
    availableSettings: r.available_settings || [],
    gameModes: r.game_modes || [],
  };
}

/** Map frontend camelCase → API snake_case (for POST/PUT body) */
function localToApi(r: GameRule): Record<string, unknown> {
  return {
    game_name: r.gameName,
    game_icon: r.gameIcon,
    game_image: r.gameImage || "",
    is_active: r.isActive,
    all_maps: r.allMaps,
    all_team_modes: r.allTeamModes,
    all_player_counts: r.allPlayerCounts,
    player_fields: r.playerFields,
    available_settings: r.availableSettings,
    game_modes: r.gameModes,
  };
}

// Sortable Tag Item - Entire tag is draggable
function SortableTag({
  id,
  children,
  onRemove,
  image,
}: {
  id: string;
  children: React.ReactNode;
  onRemove: () => void;
  image?: string;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
    useSortable({ id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`bl-sortable-tag ${isDragging ? "dragging" : ""}`}
    >
      <div className="bl-tag-drag" {...attributes} {...listeners}>
        <GripVertical size={14} />
      </div>
      {image && (
        <div className="bl-tag-image">
          <img src={image} alt="" />
        </div>
      )}
      <span className="bl-tag-text">{children}</span>
      <button
        className="bl-tag-remove"
        onClick={(e) => {
          e.stopPropagation();
          onRemove();
        }}
        onPointerDown={(e) => e.stopPropagation()}
      >
        <X size={14} />
      </button>
    </div>
  );
}

// Sortable Game Mode Item
function SortableGameMode({
  mode,
  isExpanded,
  onToggle,
  onRemove,
  children,
}: {
  mode: GameModeConfig;
  isExpanded: boolean;
  onToggle: () => void;
  onRemove: () => void;
  children: React.ReactNode;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
    useSortable({ id: mode.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`bl-mode-card ${isExpanded ? "expanded" : ""} ${isDragging ? "dragging" : ""}`}
    >
      <div className="bl-mode-header" onClick={onToggle}>
        <div className="bl-mode-drag" {...attributes} {...listeners}>
          <GripVertical size={18} />
        </div>
        <span className="bl-mode-title">{mode.name}</span>
        <div className="bl-mode-stats">
          <span className="bl-mode-stat">
            <Map size={14} />
            {mode.allowedMaps.length}
          </span>
          <span className="bl-mode-stat">
            <Users size={14} />
            {mode.allowedTeamModes.length}
          </span>
          <span className="bl-mode-stat">
            <UserPlus size={14} />
            {mode.allowedPlayerCounts.length}
          </span>
        </div>
        <button
          className="bl-mode-delete"
          onClick={(e) => {
            e.stopPropagation();
            onRemove();
          }}
        >
          <Trash2 size={16} />
        </button>
        <div className="bl-mode-chevron">
          {isExpanded ? <ChevronUp size={20} /> : <ChevronDown size={20} />}
        </div>
      </div>
      {isExpanded && <div className="bl-mode-content">{children}</div>}
    </div>
  );
}

// View modes
type ViewMode = "list" | "edit" | "add";

function Rules() {
  const [gameRules, setGameRules] = useState<GameRule[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [viewMode, setViewMode] = useState<ViewMode>("list");
  const [editingRule, setEditingRule] = useState<GameRule | null>(null);
  const [expandedModes, setExpandedModes] = useState<string[]>([]);
  const [toast, setToast] = useState<{
    message: string;
    type: "success" | "error" | "info";
  } | null>(null);

  // ── Fetch rules from API ──
  const fetchRules = useCallback(async () => {
    try {
      setIsLoading(true);
      const res = await apiFetch<{ success: boolean; rules: ApiRule[] }>({
        path: "/battle-ledger/v1/rules",
      });
      if (res.success) {
        setGameRules(res.rules.map(apiToLocal));
      }
    } catch (err: any) {
      showToast(err?.message || "Failed to load rules", "error");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRules();
  }, [fetchRules]);

  // Form state
  const [formData, setFormData] = useState<GameRule>({
    id: "",
    gameName: "",
    gameIcon: "",
    gameImage: "",
    gameModes: [],
    allMaps: [],
    allTeamModes: [],
    allPlayerCounts: [],
    isActive: true,
    playerFields: [],
    availableSettings: [],
  });

  // Input states
  const [newMapInput, setNewMapInput] = useState("");
  const [newTeamModeInput, setNewTeamModeInput] = useState({ name: "", playersPerTeam: 1 });
  const [newPlayerCountInput, setNewPlayerCountInput] = useState("");
  const [newGameModeInput, setNewGameModeInput] = useState("");
  const [newSettingInput, setNewSettingInput] = useState("");
  const [newFieldInput, setNewFieldInput] = useState({ name: "", type: "text" as "text" | "number" | "email", placeholder: "" });

  // DnD Sensors
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const showToast = (message: string, type: "success" | "error" | "info") => {
    setToast({ message, type });
  };

  // Helper: Calculate max teams based on player count and team size
  const calculateMaxTeams = (playerCount: number, playersPerTeam: number): number => {
    return Math.floor(playerCount / playersPerTeam);
  };

  // Helper: Get team mode by ID
  const getTeamModeById = (teamModeId: string): TeamModeConfig | undefined => {
    return formData.allTeamModes.find((tm) => tm.id === teamModeId);
  };

  // WordPress Media Library
  const openMediaLibrary = (callback: (url: string) => void) => {
    if (typeof wp !== "undefined" && wp.media) {
      const mediaUploader = wp.media({
        title: "Select Image",
        button: { text: "Use Image" },
        multiple: false,
      });

      mediaUploader.on("select", () => {
        const attachment = mediaUploader.state().get("selection").toJSON()[0];
        callback(attachment.url);
      });

      mediaUploader.open();
    } else {
      // Fallback for development
      const url = prompt("Enter image URL:");
      if (url) callback(url);
    }
  };

  const handleEdit = (rule: GameRule) => {
    setEditingRule(rule);
    setFormData({ ...rule });
    setViewMode("edit");
    setExpandedModes([]);
  };

  const handleAddNew = () => {
    setEditingRule(null);
    setFormData({
      id: 0,
      gameName: "",
      gameIcon: "",
      gameImage: "",
      gameModes: [],
      allMaps: [],
      allTeamModes: [],
      allPlayerCounts: [],
      isActive: true,
      playerFields: [],
      availableSettings: [],
    });
    setViewMode("add");
    setExpandedModes([]);
  };

  const handleBack = () => {
    setViewMode("list");
    setEditingRule(null);
  };

  const handleSave = async () => {
    if (!formData.gameName.trim()) {
      showToast("Please enter a game name", "error");
      return;
    }

    if (formData.gameModes.length === 0) {
      showToast("Please add at least one game mode", "error");
      return;
    }

    setIsSaving(true);
    try {
      if (viewMode === "add") {
        const res = await apiFetch<{ success: boolean; rule: ApiRule; message: string }>({
          path: "/battle-ledger/v1/rules",
          method: "POST",
          data: localToApi(formData),
        });
        if (res.success) {
          setGameRules([...gameRules, apiToLocal(res.rule)]);
          showToast(res.message || `${formData.gameName} created successfully!`, "success");
          invalidateRulesCache();
          eventBus.emit("rules:changed");
        }
      } else if (editingRule) {
        const res = await apiFetch<{ success: boolean; rule: ApiRule; message: string }>({
          path: `/battle-ledger/v1/rules/${editingRule.id}`,
          method: "PUT",
          data: localToApi(formData),
        });
        if (res.success) {
          setGameRules(
            gameRules.map((r) => (r.id === editingRule.id ? apiToLocal(res.rule) : r))
          );
          showToast(res.message || `${formData.gameName} updated successfully!`, "success");
          invalidateRulesCache();
          eventBus.emit("rules:changed");
        }
      }
      setViewMode("list");
      setEditingRule(null);
    } catch (err: any) {
      showToast(err?.message || "Failed to save", "error");
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async (id: number | string, name: string) => {
    if (confirm(`Are you sure you want to delete "${name}"?`)) {
      try {
        await apiFetch<{ success: boolean }>({
          path: `/battle-ledger/v1/rules/${id}`,
          method: "DELETE",
        });
        setGameRules(gameRules.filter((r) => r.id !== id));
        showToast(`${name} deleted`, "success");
        invalidateRulesCache();
        eventBus.emit("rules:changed");
      } catch (err: any) {
        showToast(err?.message || "Failed to delete", "error");
      }
    }
  };

  const handleDuplicate = async (rule: GameRule) => {
    try {
      const res = await apiFetch<{ success: boolean; rule: ApiRule; message: string }>({
        path: `/battle-ledger/v1/rules/${rule.id}/duplicate`,
        method: "POST",
      });
      if (res.success) {
        setGameRules([...gameRules, apiToLocal(res.rule)]);
        showToast(res.message || `${rule.gameName} duplicated`, "success");
        invalidateRulesCache();
        eventBus.emit("rules:changed");
      }
    } catch (err: any) {
      showToast(err?.message || "Failed to duplicate", "error");
    }
  };

  const toggleActive = async (id: number | string, name: string) => {
    try {
      const res = await apiFetch<{ success: boolean; is_active: boolean; message: string }>({
        path: `/battle-ledger/v1/rules/${id}/toggle`,
        method: "POST",
      });
      if (res.success) {
        setGameRules(
          gameRules.map((r) => (r.id === id ? { ...r, isActive: res.is_active } : r))
        );
        showToast(res.message || `${name} toggled`, "success");
      }
    } catch (err: any) {
      showToast(err?.message || "Failed to toggle", "error");
    }
  };

  const toggleModeExpanded = (modeId: string) => {
    setExpandedModes((prev) =>
      prev.includes(modeId)
        ? prev.filter((id) => id !== modeId)
        : [...prev, modeId]
    );
  };

  // Add Map with optional image
  const addMap = (image?: string) => {
    if (newMapInput.trim()) {
      const mapId = newMapInput.toLowerCase().replace(/\s+/g, "-");
      if (!formData.allMaps.find((m) => m.id === mapId)) {
        setFormData({
          ...formData,
          allMaps: [
            ...formData.allMaps,
            { id: mapId, name: newMapInput.trim(), image },
          ],
        });
        setNewMapInput("");
        showToast(`Map "${newMapInput.trim()}" added`, "success");
      }
    }
  };

  const addTeamMode = () => {
    const name = newTeamModeInput.name.trim();
    const playersPerTeam = newTeamModeInput.playersPerTeam;
    if (
      name &&
      playersPerTeam > 0 &&
      !formData.allTeamModes.some((tm) => tm.name.toLowerCase() === name.toLowerCase())
    ) {
      const newMode: TeamModeConfig = {
        id: name.toLowerCase().replace(/\s+/g, "-"),
        name,
        playersPerTeam,
      };
      setFormData({
        ...formData,
        allTeamModes: [...formData.allTeamModes, newMode],
      });
      setNewTeamModeInput({ name: "", playersPerTeam: 1 });
      showToast(`Team mode "${name}" (${playersPerTeam} players) added`, "success");
    }
  };

  const addPlayerCount = () => {
    const num = parseInt(newPlayerCountInput);
    if (!isNaN(num) && num > 0 && !formData.allPlayerCounts.includes(num)) {
      setFormData({
        ...formData,
        allPlayerCounts: [...formData.allPlayerCounts, num].sort(
          (a, b) => b - a
        ),
      });
      setNewPlayerCountInput("");
      showToast(`Player count ${num} added`, "success");
    }
  };

  const addGameMode = () => {
    if (newGameModeInput.trim()) {
      const newMode: GameModeConfig = {
        id: newGameModeInput.toLowerCase().replace(/\s+/g, "-"),
        name: newGameModeInput.trim(),
        allowedMaps: [],
        allowedTeamModes: [],
        allowedPlayerCounts: [],
        settings: formData.availableSettings.map(s => ({ ...s })),
      };
      setFormData({
        ...formData,
        gameModes: [...formData.gameModes, newMode],
      });
      setExpandedModes([...expandedModes, newMode.id]);
      setNewGameModeInput("");
      showToast(`Game mode "${newGameModeInput.trim()}" added`, "success");
    }
  };

  // Add Player Field
  const addPlayerField = () => {
    if (newFieldInput.name.trim()) {
      const newField: PlayerFieldConfig = {
        id: newFieldInput.name.toLowerCase().replace(/\s+/g, "-"),
        name: newFieldInput.name.trim(),
        type: newFieldInput.type,
        placeholder: newFieldInput.placeholder || `Enter ${newFieldInput.name}`,
        required: true,
      };
      setFormData({
        ...formData,
        playerFields: [...formData.playerFields, newField],
      });
      setNewFieldInput({ name: "", type: "text", placeholder: "" });
      showToast(`Field "${newFieldInput.name}" added`, "success");
    }
  };

  // Add Game Setting
  const addGameSetting = () => {
    if (newSettingInput.trim()) {
      const newSetting: GameSettingConfig = {
        id: newSettingInput.toLowerCase().replace(/\s+/g, "-"),
        name: newSettingInput.trim(),
        enabled: true,
      };
      setFormData({
        ...formData,
        availableSettings: [...formData.availableSettings, newSetting],
      });
      setNewSettingInput("");
      showToast(`Setting "${newSettingInput}" added`, "success");
    }
  };

  // Toggle mode setting
  const toggleModeSetting = (modeId: string, settingId: string) => {
    setFormData({
      ...formData,
      gameModes: formData.gameModes.map((mode) => {
        if (mode.id === modeId) {
          return {
            ...mode,
            settings: mode.settings.map((s) =>
              s.id === settingId ? { ...s, enabled: !s.enabled } : s
            ),
          };
        }
        return mode;
      }),
    });
  };

  const removeGameMode = (modeId: string) => {
    setFormData({
      ...formData,
      gameModes: formData.gameModes.filter((m) => m.id !== modeId),
    });
    showToast("Game mode removed", "info");
  };

  // Toggle relationship
  const toggleRelationship = (
    modeId: string,
    field: "allowedMaps" | "allowedTeamModes" | "allowedPlayerCounts",
    value: string | number
  ) => {
    setFormData({
      ...formData,
      gameModes: formData.gameModes.map((mode) => {
        if (mode.id === modeId) {
          const currentArray = mode[field] as (string | number)[];
          const newArray = currentArray.includes(value)
            ? currentArray.filter((v) => v !== value)
            : [...currentArray, value];
          return { ...mode, [field]: newArray };
        }
        return mode;
      }),
    });
  };

  const selectAll = (
    modeId: string,
    field: "allowedMaps" | "allowedTeamModes" | "allowedPlayerCounts"
  ) => {
    const allValues =
      field === "allowedMaps"
        ? formData.allMaps.map((m) => m.id)
        : field === "allowedTeamModes"
        ? formData.allTeamModes.map((tm) => tm.id)
        : formData.allPlayerCounts;

    setFormData({
      ...formData,
      gameModes: formData.gameModes.map((mode) => {
        if (mode.id === modeId) {
          return { ...mode, [field]: [...allValues] };
        }
        return mode;
      }),
    });
  };

  const clearAll = (
    modeId: string,
    field: "allowedMaps" | "allowedTeamModes" | "allowedPlayerCounts"
  ) => {
    setFormData({
      ...formData,
      gameModes: formData.gameModes.map((mode) => {
        if (mode.id === modeId) {
          return { ...mode, [field]: [] };
        }
        return mode;
      }),
    });
  };

  // Handle drag end
  const handleMapDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = formData.allMaps.findIndex((m) => m.id === active.id);
      const newIndex = formData.allMaps.findIndex((m) => m.id === over.id);
      setFormData({
        ...formData,
        allMaps: arrayMove(formData.allMaps, oldIndex, newIndex),
      });
    }
  };

  const handleTeamModeDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = formData.allTeamModes.findIndex((tm) => tm.id === active.id);
      const newIndex = formData.allTeamModes.findIndex((tm) => tm.id === over.id);
      setFormData({
        ...formData,
        allTeamModes: arrayMove(formData.allTeamModes, oldIndex, newIndex),
      });
    }
  };

  const handleGameModeDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = formData.gameModes.findIndex((m) => m.id === active.id);
      const newIndex = formData.gameModes.findIndex((m) => m.id === over.id);
      setFormData({
        ...formData,
        gameModes: arrayMove(formData.gameModes, oldIndex, newIndex),
      });
    }
  };

  // Render skeleton loading cards
  const renderSkeletons = () => (
    <div className="bl-rules-grid">
      {[1, 2].map((i) => (
        <div key={i} className="bl-game-card bl-skeleton-card">
          <div className="bl-game-card-header">
            <div className="bl-skeleton bl-skeleton-avatar" />
            <div className="bl-game-info">
              <div className="bl-skeleton bl-skeleton-title" />
              <div className="bl-skeleton bl-skeleton-badge" />
            </div>
          </div>
          <div className="bl-game-card-body">
            <div className="bl-game-stats">
              <div className="bl-skeleton bl-skeleton-stat" />
              <div className="bl-skeleton bl-skeleton-stat" />
              <div className="bl-skeleton bl-skeleton-stat" />
            </div>
            <div className="bl-game-modes-list">
              <div className="bl-skeleton bl-skeleton-mode" />
              <div className="bl-skeleton bl-skeleton-mode" />
              <div className="bl-skeleton bl-skeleton-mode" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );

  // Render List View
  const renderListView = () => (
    <>
      <div className="bl-page-header">
        <div>
          <h2>
            <Settings2 size={24} className="bl-header-icon" />
            Rules Engine
          </h2>
          <p>Configure game templates with mode-based relationships</p>
        </div>
        <button className="bl-btn bl-btn-primary" onClick={handleAddNew}>
          <Plus size={18} />
          Add Game
        </button>
      </div>

      {isLoading ? renderSkeletons() : gameRules.length === 0 ? (
        <div className="bl-rules-empty">
          <div className="bl-rules-empty-inner">
            <div className="bl-rules-empty-icon">
              <div className="bl-rules-empty-ring" />
              <Gamepad2 size={40} />
            </div>
            <h3>No game rules configured</h3>
            <p>Set up your first game with maps, team modes, and tournament rules to start creating tournaments.</p>
            <button className="bl-btn bl-btn-primary" onClick={handleAddNew}>
              <Plus size={18} />
              Create First Game
            </button>
          </div>
        </div>
      ) : (
      <div className="bl-rules-grid">
        {gameRules.map((rule) => (
          <div
            key={rule.id}
            className={`bl-game-card ${!rule.isActive ? "inactive" : ""}`}
          >
            <div className="bl-game-card-header">
              <div className="bl-game-card-header-bg" />
              {rule.gameImage ? (
                <img src={rule.gameImage} alt={rule.gameName} className="bl-game-image" />
              ) : (
                <div className="bl-game-icon"><Gamepad2 size={28} /></div>
              )}
              <div className="bl-game-info">
                <h4>{rule.gameName}</h4>
                <span className={`bl-status-pill ${rule.isActive ? "active" : ""}`}>
                  {rule.isActive ? "Active" : "Inactive"}
                </span>
              </div>
              <div className="bl-game-actions">
                <button
                  className="bl-icon-btn"
                  onClick={() => toggleActive(rule.id, rule.gameName)}
                  title={rule.isActive ? "Deactivate" : "Activate"}
                >
                  {rule.isActive ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
                <button
                  className="bl-icon-btn"
                  onClick={() => handleDuplicate(rule)}
                  title="Duplicate"
                >
                  <Copy size={16} />
                </button>
                <button
                  className="bl-icon-btn"
                  onClick={() => handleEdit(rule)}
                  title="Edit"
                >
                  <Edit3 size={16} />
                </button>
                <button
                  className="bl-icon-btn danger"
                  onClick={() => handleDelete(rule.id, rule.gameName)}
                  title="Delete"
                >
                  <Trash2 size={16} />
                </button>
              </div>
            </div>

            <div className="bl-game-card-body">
              <div className="bl-game-stats">
                <div className="bl-game-stat">
                  <Gamepad2 size={15} />
                  <span><strong>{rule.gameModes.length}</strong> Modes</span>
                </div>
                <div className="bl-game-stat">
                  <Map size={15} />
                  <span><strong>{rule.allMaps.length}</strong> Maps</span>
                </div>
                <div className="bl-game-stat">
                  <Users size={15} />
                  <span><strong>{rule.allTeamModes.length}</strong> Teams</span>
                </div>
                <div className="bl-game-stat">
                  <UserPlus size={15} />
                  <span><strong>{rule.allPlayerCounts.length}</strong> Player Counts</span>
                </div>
              </div>

              <div className="bl-game-modes-list">
                {rule.gameModes.slice(0, 3).map((mode) => {
                  const teamModeNames = mode.allowedTeamModes
                    .map((tmId) => rule.allTeamModes.find((tm) => tm.id === tmId)?.name)
                    .filter(Boolean)
                    .join(", ");
                  return (
                    <div key={mode.id} className="bl-game-mode-item">
                      <div className="bl-game-mode-dot" />
                      <div className="bl-game-mode-info">
                        <span className="bl-game-mode-name">{mode.name}</span>
                        <span className="bl-game-mode-meta">
                          {mode.allowedMaps.length} maps &middot; {teamModeNames || "No teams"} &middot; {mode.allowedPlayerCounts.join(", ")} players
                        </span>
                      </div>
                    </div>
                  );
                })}
                {rule.gameModes.length > 3 && (
                  <div className="bl-game-mode-more">
                    +{rule.gameModes.length - 3} more modes
                  </div>
                )}
              </div>

              <button className="bl-game-edit-full" onClick={() => handleEdit(rule)}>
                <Edit3 size={14} />
                Edit Configuration
              </button>
            </div>
          </div>
        ))}
      </div>
      )}
    </>
  );

  // Render Edit/Add View
  const renderEditView = () => (
    <>
      {/* Header with Back Button */}
      <div className="bl-edit-header">
        <button className="bl-back-btn" onClick={handleBack}>
          <ArrowLeft size={18} />
          <span>Back</span>
        </button>
        <div className="bl-edit-header-info">
          <h2>{viewMode === "add" ? "Create New Game" : `Edit: ${editingRule?.gameName}`}</h2>
          <p className="bl-edit-header-sub">
            {viewMode === "add"
              ? "Configure maps, team modes, and tournament rules for a new game."
              : "Modify the game configuration, maps, modes, and rule relationships."}
          </p>
        </div>
      </div>

      <div className="bl-edit-form">
        {/* Basic Info Section */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <Gamepad2 size={18} />
              Basic Information
            </h3>
            <p>Give your game a name and optionally upload an icon image.</p>
          </div>
          <div className="bl-form-row">
            <div className="bl-form-field flex-1">
              <label>Game Name</label>
              <input
                type="text"
                value={formData.gameName}
                onChange={(e) =>
                  setFormData({ ...formData, gameName: e.target.value })
                }
                placeholder="Enter game name..."
              />
            </div>
            <div className="bl-form-field">
              <label>Icon</label>
              <div className="bl-icon-picker">
                {formData.gameImage ? (
                  <div className="bl-icon-preview has-image">
                    <img src={formData.gameImage} alt="" />
                    <button
                      className="bl-icon-remove"
                      onClick={() => setFormData({ ...formData, gameImage: "" })}
                    >
                      <X size={14} />
                    </button>
                  </div>
                ) : (
                  <div className="bl-icon-preview">
                    <Gamepad2 size={32} className="bl-icon-default" />
                  </div>
                )}
                <div className="bl-icon-actions">
                  <button
                    className="bl-upload-btn"
                    onClick={() =>
                      openMediaLibrary((url) =>
                        setFormData({ ...formData, gameImage: url })
                      )
                    }
                  >
                    <Upload size={16} />
                    <span>Upload</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Maps Section */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <Map size={18} />
              Available Maps
            </h3>
            <p>Add all maps for this game. Drag to reorder.</p>
          </div>

          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleMapDragEnd}
          >
            <SortableContext
              items={formData.allMaps.map((m) => m.id)}
              strategy={horizontalListSortingStrategy}
            >
              <div className="bl-tags-container">
                {formData.allMaps.map((map) => (
                  <SortableTag
                    key={map.id}
                    id={map.id}
                    image={map.image}
                    onRemove={() =>
                      setFormData({
                        ...formData,
                        allMaps: formData.allMaps.filter((m) => m.id !== map.id),
                      })
                    }
                  >
                    {map.name}
                  </SortableTag>
                ))}
              </div>
            </SortableContext>
          </DndContext>

          <div className="bl-add-row">
            <input
              type="text"
              value={newMapInput}
              onChange={(e) => setNewMapInput(e.target.value)}
              placeholder="Add map name..."
              onKeyDown={(e) => e.key === "Enter" && addMap()}
            />
            <button
              className="bl-add-with-image"
              onClick={() =>
                openMediaLibrary((url) => addMap(url))
              }
              title="Add with image"
            >
              <Image size={18} />
            </button>
            <button className="bl-add-btn" onClick={() => addMap()}>
              <Plus size={18} />
              Add
            </button>
          </div>
        </div>

        {/* Team Modes Section */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <Users size={18} />
              Team Modes
            </h3>
            <p>Define available team configurations.</p>
          </div>

          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleTeamModeDragEnd}
          >
            <SortableContext
              items={formData.allTeamModes.map(tm => tm.id)}
              strategy={horizontalListSortingStrategy}
            >
              <div className="bl-tags-container">
                {formData.allTeamModes.map((mode) => (
                  <SortableTag
                    key={mode.id}
                    id={mode.id}
                    onRemove={() =>
                      setFormData({
                        ...formData,
                        allTeamModes: formData.allTeamModes.filter(
                          (m) => m.id !== mode.id
                        ),
                      })
                    }
                  >
                    <span className="bl-team-mode-name">{mode.name}</span>
                    <span className="bl-team-mode-size">{mode.playersPerTeam}p</span>
                  </SortableTag>
                ))}
              </div>
            </SortableContext>
          </DndContext>

          <div className="bl-add-row bl-add-row-team">
            <input
              type="text"
              value={newTeamModeInput.name}
              onChange={(e) => setNewTeamModeInput({ ...newTeamModeInput, name: e.target.value })}
              placeholder="Mode name (e.g., Squad)"
              onKeyDown={(e) => e.key === "Enter" && addTeamMode()}
            />
            <input
              type="number"
              className="bl-team-size-input"
              value={newTeamModeInput.playersPerTeam}
              onChange={(e) => setNewTeamModeInput({ ...newTeamModeInput, playersPerTeam: parseInt(e.target.value) || 1 })}
              placeholder="Players"
              min={1}
              max={100}
            />
            <button className="bl-add-btn" onClick={addTeamMode}>
              <Plus size={18} />
              Add
            </button>
          </div>
        </div>

        {/* Player Counts Section */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <UserPlus size={18} />
              Player Counts
            </h3>
            <p>Define available player slots for tournaments.</p>
          </div>

          <div className="bl-tags-container">
            {formData.allPlayerCounts.map((count) => (
              <div key={count} className="bl-player-tag">
                <span>{count} players</span>
                <button
                  className="bl-tag-remove"
                  onClick={() =>
                    setFormData({
                      ...formData,
                      allPlayerCounts: formData.allPlayerCounts.filter(
                        (c) => c !== count
                      ),
                    })
                  }
                >
                  <X size={14} />
                </button>
              </div>
            ))}
          </div>

          <div className="bl-add-row">
            <input
              type="number"
              value={newPlayerCountInput}
              onChange={(e) => setNewPlayerCountInput(e.target.value)}
              placeholder="Add player count..."
              onKeyDown={(e) => e.key === "Enter" && addPlayerCount()}
            />
            <button className="bl-add-btn" onClick={addPlayerCount}>
              <Plus size={18} />
              Add
            </button>
          </div>
        </div>

        {/* Player Identity Fields Section */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <FileText size={18} />
              Player Identity Fields
            </h3>
            <p>Define what information players must provide when joining tournaments.</p>
          </div>

          <div className="bl-fields-list">
            {formData.playerFields.map((field) => (
              <div key={field.id} className="bl-field-item">
                <div className="bl-field-icon">
                  {field.type === "number" ? <Hash size={16} /> : field.type === "email" ? <AtSign size={16} /> : <Type size={16} />}
                </div>
                <div className="bl-field-info">
                  <span className="bl-field-name">{field.name}</span>
                  <span className="bl-field-type">{field.type} • {field.required ? "Required" : "Optional"}</span>
                </div>
                <button
                  className="bl-tag-remove"
                  onClick={() =>
                    setFormData({
                      ...formData,
                      playerFields: formData.playerFields.filter((f) => f.id !== field.id),
                    })
                  }
                >
                  <X size={14} />
                </button>
              </div>
            ))}
          </div>

          <div className="bl-add-field-row">
            <input
              type="text"
              value={newFieldInput.name}
              onChange={(e) => setNewFieldInput({ ...newFieldInput, name: e.target.value })}
              placeholder="Field name (e.g., Player UID)"
              className="bl-field-name-input"
            />
            <Dropdown
              value={newFieldInput.type}
              onChange={(val) => setNewFieldInput({ ...newFieldInput, type: val as "text" | "number" | "email" })}
              options={[
                { value: "text", label: "Text", icon: <Type size={14} /> },
                { value: "number", label: "Number", icon: <Hash size={14} /> },
                { value: "email", label: "Email", icon: <AtSign size={14} /> },
              ]}
              className="bl-field-type-dropdown"
            />
            <input
              type="text"
              value={newFieldInput.placeholder}
              onChange={(e) => setNewFieldInput({ ...newFieldInput, placeholder: e.target.value })}
              placeholder="Placeholder text"
              className="bl-field-placeholder-input"
            />
            <button className="bl-add-btn" onClick={addPlayerField}>
              <Plus size={18} />
              Add Field
            </button>
          </div>
        </div>

        {/* Game Settings Section */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <ToggleLeft size={18} />
              Available Game Settings
            </h3>
            <p>Define toggleable settings for competitive rules (can be enabled/disabled per game mode).</p>
          </div>

          <div className="bl-settings-list">
            {formData.availableSettings.map((setting) => (
              <div key={setting.id} className="bl-setting-item">
                <div className="bl-setting-info">
                  <span className="bl-setting-name">{setting.name}</span>
                </div>
                <button
                  className="bl-tag-remove"
                  onClick={() =>
                    setFormData({
                      ...formData,
                      availableSettings: formData.availableSettings.filter((s) => s.id !== setting.id),
                    })
                  }
                >
                  <X size={14} />
                </button>
              </div>
            ))}
          </div>

          <div className="bl-add-row">
            <input
              type="text"
              value={newSettingInput}
              onChange={(e) => setNewSettingInput(e.target.value)}
              placeholder="Add setting (e.g., Gun Attributes, Emulator Block)"
              onKeyDown={(e) => e.key === "Enter" && addGameSetting()}
            />
            <button className="bl-add-btn" onClick={addGameSetting}>
              <Plus size={18} />
              Add Setting
            </button>
          </div>
        </div>

        {/* Game Modes & Relationships */}
        <div className="bl-form-card">
          <div className="bl-form-card-header">
            <h3>
              <Link2 size={18} />
              Game Modes & Relationships
            </h3>
            <p>Configure which maps, team modes, and player counts are available for each game mode.</p>
          </div>

          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleGameModeDragEnd}
          >
            <SortableContext
              items={formData.gameModes.map((m) => m.id)}
              strategy={verticalListSortingStrategy}
            >
              <div className="bl-modes-container">
                {formData.gameModes.map((mode) => (
                  <SortableGameMode
                    key={mode.id}
                    mode={mode}
                    isExpanded={expandedModes.includes(mode.id)}
                    onToggle={() => toggleModeExpanded(mode.id)}
                    onRemove={() => removeGameMode(mode.id)}
                  >
                    {/* Maps Config */}
                    <div className="bl-config-block">
                      <div className="bl-config-block-header">
                        <label>
                          <Map size={14} />
                          Allowed Maps
                        </label>
                        <div className="bl-config-block-actions">
                          <button onClick={() => selectAll(mode.id, "allowedMaps")}>
                            Select All
                          </button>
                          <button onClick={() => clearAll(mode.id, "allowedMaps")}>
                            Clear
                          </button>
                        </div>
                      </div>
                      <div className="bl-toggle-grid">
                        {formData.allMaps.map((map) => (
                          <label key={map.id} className="bl-toggle-item">
                            <input
                              type="checkbox"
                              checked={mode.allowedMaps.includes(map.id)}
                              onChange={() =>
                                toggleRelationship(mode.id, "allowedMaps", map.id)
                              }
                            />
                            <span className="bl-toggle-check"></span>
                            {map.image && (
                              <img src={map.image} alt="" className="bl-toggle-image" />
                            )}
                            <span className="bl-toggle-label">{map.name}</span>
                          </label>
                        ))}
                      </div>
                    </div>

                    {/* Team Modes Config */}
                    <div className="bl-config-block">
                      <div className="bl-config-block-header">
                        <label>
                          <Users size={14} />
                          Allowed Team Modes
                        </label>
                        <div className="bl-config-block-actions">
                          <button onClick={() => selectAll(mode.id, "allowedTeamModes")}>
                            Select All
                          </button>
                          <button onClick={() => clearAll(mode.id, "allowedTeamModes")}>
                            Clear
                          </button>
                        </div>
                      </div>
                      <div className="bl-toggle-grid">
                        {formData.allTeamModes.map((teamMode) => (
                          <label key={teamMode.id} className="bl-toggle-item">
                            <input
                              type="checkbox"
                              checked={mode.allowedTeamModes.includes(teamMode.id)}
                              onChange={() =>
                                toggleRelationship(mode.id, "allowedTeamModes", teamMode.id)
                              }
                            />
                            <span className="bl-toggle-check"></span>
                            <span className="bl-toggle-label">
                              {teamMode.name}
                              <span className="bl-team-size-badge">{teamMode.playersPerTeam}p</span>
                            </span>
                          </label>
                        ))}
                      </div>
                    </div>

                    {/* Player Counts Config with Team Validation */}
                    <div className="bl-config-block">
                      <div className="bl-config-block-header">
                        <label>
                          <UserPlus size={14} />
                          Allowed Player Counts
                        </label>
                        <div className="bl-config-block-actions">
                          <button onClick={() => selectAll(mode.id, "allowedPlayerCounts")}>
                            Select All
                          </button>
                          <button onClick={() => clearAll(mode.id, "allowedPlayerCounts")}>
                            Clear
                          </button>
                        </div>
                      </div>
                      
                      {/* Show team validation for selected player counts */}
                      {mode.allowedPlayerCounts.length > 0 && mode.allowedTeamModes.length > 0 && (
                        <div className="bl-team-validation-grid">
                          {mode.allowedPlayerCounts.map((count) => (
                            <div key={count} className="bl-team-validation-row">
                              <span className="bl-tv-players">{count} players</span>
                              <div className="bl-tv-calculations">
                                {mode.allowedTeamModes.map((tmId) => {
                                  const teamMode = getTeamModeById(tmId);
                                  if (!teamMode) return null;
                                  const maxTeams = calculateMaxTeams(count, teamMode.playersPerTeam);
                                  const remainder = count % teamMode.playersPerTeam;
                                  return (
                                    <span
                                      key={tmId}
                                      className={`bl-tv-calc ${remainder > 0 ? "has-remainder" : ""}`}
                                      title={remainder > 0 ? `${remainder} player(s) won't fill a team` : "Exact fit"}
                                    >
                                      {teamMode.name}: {maxTeams} teams
                                    </span>
                                  );
                                })}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}

                      <div className="bl-toggle-grid">
                        {formData.allPlayerCounts.map((count) => (
                          <label key={count} className="bl-toggle-item">
                            <input
                              type="checkbox"
                              checked={mode.allowedPlayerCounts.includes(count)}
                              onChange={() =>
                                toggleRelationship(mode.id, "allowedPlayerCounts", count)
                              }
                            />
                            <span className="bl-toggle-check"></span>
                            <span className="bl-toggle-label">{count} players</span>
                          </label>
                        ))}
                      </div>
                    </div>

                    {/* Game Settings for this Mode */}
                    {mode.settings && mode.settings.length > 0 && (
                      <div className="bl-config-block">
                        <div className="bl-config-block-header">
                          <label>
                            <ToggleLeft size={14} />
                            Mode Settings
                          </label>
                        </div>
                        <div className="bl-settings-toggle-grid">
                          {mode.settings.map((setting) => (
                            <label key={setting.id} className="bl-setting-toggle">
                              <input
                                type="checkbox"
                                checked={setting.enabled}
                                onChange={() => toggleModeSetting(mode.id, setting.id)}
                              />
                              <span className="bl-setting-toggle-slider"></span>
                              <span className="bl-setting-toggle-label">{setting.name}</span>
                            </label>
                          ))}
                        </div>
                      </div>
                    )}

                  </SortableGameMode>
                ))}
              </div>
            </SortableContext>
          </DndContext>

          <div className="bl-add-row">
            <input
              type="text"
              value={newGameModeInput}
              onChange={(e) => setNewGameModeInput(e.target.value)}
              placeholder="Add new game mode..."
              onKeyDown={(e) => e.key === "Enter" && addGameMode()}
            />
            <button className="bl-add-btn bl-btn-primary" onClick={addGameMode}>
              <Plus size={18} />
              Add Game Mode
            </button>
          </div>
        </div>

        {/* Form Actions */}
        <div className="bl-form-footer">
          <button className="bl-btn bl-btn-secondary" onClick={handleBack}>
            <X size={18} />
            Cancel
          </button>
          <button className="bl-btn bl-btn-primary" onClick={handleSave} disabled={isSaving}>
            {isSaving ? <Loader2 size={18} className="bl-spin" /> : <Save size={18} />}
            {isSaving ? "Saving…" : viewMode === "add" ? "Create Game" : "Save Changes"}
          </button>
        </div>
      </div>
    </>
  );

  return (
    <>
      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}

      {viewMode === "list" ? renderListView() : renderEditView()}
    </>
  );
}

export default Rules;
