/**
 * Shared game-rules cache.
 *
 * Rules rarely change during an admin session. This hook fetches them
 * once and caches the result in a module-level variable so that every
 * tab (Tournaments, Live, Finished) shares the same data without
 * hitting the server again.
 *
 * If rules *are* changed (from the Rules Engine tab), call
 * `invalidateRulesCache()` or emit `rules:changed` and all active
 * consumers will re-fetch.
 */

import { useState, useEffect, useCallback } from "react";
import { fetchActiveRules } from "../pages/tournaments/api";
import eventBus from "./eventBus";
import type { GameRule } from "../pages/tournaments/types";

let cachedRules: GameRule[] | null = null;
let fetchPromise: Promise<GameRule[]> | null = null;

async function getRules(force = false): Promise<GameRule[]> {
  if (cachedRules && !force) return cachedRules;

  // Deduplicate concurrent calls — only one in-flight request at a time
  if (!fetchPromise || force) {
    fetchPromise = fetchActiveRules().then((rules) => {
      cachedRules = Array.isArray(rules) ? rules : [];
      fetchPromise = null;
      return cachedRules;
    });
  }
  return fetchPromise;
}

/** Force subsequent calls to re-fetch from server */
export function invalidateRulesCache() {
  cachedRules = null;
  fetchPromise = null;
}

/**
 * React hook — returns `[rules, loading]`.
 *
 * Fetches once, then shares cache. Listens for `rules:changed` events
 * to automatically re-fetch when admin edits game rules.
 */
export function useGameRules(): [GameRule[], boolean] {
  const [rules, setRules] = useState<GameRule[]>(cachedRules ?? []);
  const [loading, setLoading] = useState(!cachedRules);

  const load = useCallback(async (force = false) => {
    setLoading(true);
    try {
      const r = await getRules(force);
      setRules(r);
    } catch {
      /* silent */
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  // Re-fetch when rules change from another tab
  useEffect(() => {
    const handler = () => {
      invalidateRulesCache();
      load(true);
    };
    eventBus.on("rules:changed", handler);
    return () => eventBus.off("rules:changed", handler);
  }, [load]);

  return [rules, loading];
}
