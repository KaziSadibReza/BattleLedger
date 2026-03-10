/**
 * Lightweight in-app event bus.
 *
 * Used so that when one tab/component mutates data (creates a tournament,
 * changes status, saves winners, etc.) other tabs know about it and can
 * refresh — without constant polling.
 *
 * Usage:
 *   eventBus.emit("tournaments:changed");          // producer
 *   eventBus.on("tournaments:changed", handler);   // consumer
 *   eventBus.off("tournaments:changed", handler);  // cleanup
 */

export type BLEvent =
  | "tournaments:changed"   // any CRUD on the main tournaments table
  | "live:changed"          // status changed / participants changed on live
  | "finished:changed"      // finished tournament created or deleted
  | "rules:changed"         // game rules changed (rare)
  | "participants:changed"; // participant added/removed

type Handler = () => void;

const listeners = new Map<BLEvent, Set<Handler>>();

function on(event: BLEvent, handler: Handler) {
  if (!listeners.has(event)) listeners.set(event, new Set());
  listeners.get(event)!.add(handler);
}

function off(event: BLEvent, handler: Handler) {
  listeners.get(event)?.delete(handler);
}

function emit(event: BLEvent) {
  listeners.get(event)?.forEach((fn) => {
    try { fn(); } catch { /* swallow */ }
  });
}

const eventBus = { on, off, emit };
export default eventBus;
