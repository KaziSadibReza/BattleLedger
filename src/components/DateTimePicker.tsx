import React, { useState, useEffect, useMemo } from "react";
import { createPortal } from "react-dom";
import {
  ChevronLeft,
  ChevronRight,
  Calendar as CalendarIcon,
  Clock,
  X,
} from "lucide-react";
import Dropdown from "./Dropdown";

interface Props {
  value: string;               // "YYYY-MM-DDTHH:mm" or ""
  onChange: (val: string) => void;
  label?: string;
  placeholder?: string;
  disablePast?: boolean;       // if true, past dates & times are disabled
  autoOpen?: boolean;          // if true, open the picker on mount
}

/* ── helpers ────────────────────────────────────────────────── */
const MONTHS = [
  "January","February","March","April","May","June",
  "July","August","September","October","November","December",
];
const DAYS = ["Su","Mo","Tu","We","Th","Fr","Sa"];

const pad = (n: number) => String(n).padStart(2, "0");

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const parseVal = (v: string) => {
  const [datePart, timePart] = v.split("T");
  const [y, m, d] = (datePart || "").split("-").map(Number);
  const [hh, mm] = (timePart || "00:00").split(":").map(Number);
  return { year: y || new Date().getFullYear(), month: (m || 1) - 1, day: d || 0, hh: hh || 0, mm: mm || 0 };
};

const formatDisplay = (v: string) => {
  if (!v) return "";
  const { year, month, day, hh, mm } = parseVal(v);
  if (!day) return "";
  const suffix = hh >= 12 ? "PM" : "AM";
  const h12 = hh % 12 || 12;
  return `${MONTHS[month].slice(0, 3)} ${day}, ${year}  ${pad(h12)}:${pad(mm)} ${suffix}`;
};

const daysInMonth = (y: number, m: number) => new Date(y, m + 1, 0).getDate();
const firstDow = (y: number, m: number) => new Date(y, m, 1).getDay();

/* ── build hour / minute dropdown options ──────────────────── */
const ALL_HOURS = Array.from({ length: 24 }, (_, i) => ({
  value: String(i),
  label: `${pad(i % 12 || 12)} ${i < 12 ? "AM" : "PM"}`,
}));

const MINUTE_STEPS = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];
const ALL_MINUTES = MINUTE_STEPS.map((m) => ({
  value: String(m),
  label: pad(m),
}));

const buildHourOpts = (disablePast: boolean, isSelToday: boolean) => {
  if (!disablePast || !isSelToday) return ALL_HOURS;
  const nowH = new Date().getHours();
  return ALL_HOURS.map((o) => ({ ...o, disabled: +o.value < nowH }));
};

const buildMinuteOpts = (disablePast: boolean, isSelToday: boolean, hh: number) => {
  if (!disablePast || !isSelToday) return ALL_MINUTES;
  const now = new Date();
  if (hh > now.getHours()) return ALL_MINUTES;
  if (hh < now.getHours()) return ALL_MINUTES.map((o) => ({ ...o, disabled: true }));
  return ALL_MINUTES.map((o) => ({ ...o, disabled: +o.value <= now.getMinutes() }));
};

/* ── Component ──────────────────────────────────────────────── */
const DateTimePicker: React.FC<Props> = ({
  value,
  onChange,
  label,
  placeholder = "Select date & time…",
  disablePast = false,
  autoOpen = false,
}) => {
  const [open, setOpen] = useState(false);

  /* auto-open after parent modal animation finishes (~350ms) */
  useEffect(() => {
    if (!autoOpen) return;
    const id = setTimeout(() => setOpen(true), 350);
    return () => clearTimeout(id);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  /* calendar view state */
  const parsed = useMemo(() => parseVal(value), [value]);
  const [viewYear, setViewYear] = useState(parsed.year);
  const [viewMonth, setViewMonth] = useState(parsed.month);
  const [selDay, setSelDay] = useState(parsed.day);
  const [hh, setHh] = useState(parsed.hh);
  const [mm, setMm] = useState(parsed.mm);

  /* sync view when value changes externally */
  useEffect(() => {
    const p = parseVal(value);
    setViewYear(p.year);
    setViewMonth(p.month);
    setSelDay(p.day);
    setHh(p.hh);
    setMm(p.mm);
  }, [value]);

  /* close on Escape */
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => { if (e.key === "Escape") setOpen(false); };
    document.addEventListener("keydown", handler);
    return () => document.removeEventListener("keydown", handler);
  }, [open]);

  /* build calendar grid */
  const totalDays = daysInMonth(viewYear, viewMonth);
  const startDow = firstDow(viewYear, viewMonth);
  const cells: (number | null)[] = Array(startDow).fill(null);
  for (let d = 1; d <= totalDays; d++) cells.push(d);

  const todayStr = today();
  const [tY, tM, tD] = todayStr.split("-").map(Number);

  const isToday = (d: number) =>
    viewYear === tY && viewMonth === tM - 1 && d === tD;

  const isSelected = (d: number) =>
    selDay === d;

  /* check if a calendar day is in the past */
  const isDayPast = (d: number) => {
    if (!disablePast) return false;
    const cellDate = new Date(viewYear, viewMonth, d);
    const todayDate = new Date(tY, tM - 1, tD);
    return cellDate < todayDate;
  };

  /* is the selected day today? (for disabling past times) */
  const isSelToday = viewYear === tY && viewMonth === tM - 1 && selDay === tD;
  const hourOpts = useMemo(() => buildHourOpts(disablePast, isSelToday), [disablePast, isSelToday]);
  const minOpts = useMemo(() => buildMinuteOpts(disablePast, isSelToday, hh), [disablePast, isSelToday, hh]);

  const prevMonth = () => {
    if (viewMonth === 0) { setViewMonth(11); setViewYear((y) => y - 1); }
    else setViewMonth((m) => m - 1);
  };

  const nextMonth = () => {
    if (viewMonth === 11) { setViewMonth(0); setViewYear((y) => y + 1); }
    else setViewMonth((m) => m + 1);
  };

  const pickDay = (d: number) => {
    setSelDay(d);
    commit(d, hh, mm);
  };

  const commit = (day: number, h: number, m: number) => {
    if (!day) return;
    const iso = `${viewYear}-${pad(viewMonth + 1)}-${pad(day)}T${pad(h)}:${pad(m)}`;
    onChange(iso);
  };

  const changeHour = (val: string) => { const h = +val; setHh(h); commit(selDay, h, mm); };
  const changeMin = (val: string) => { const m = +val; setMm(m); commit(selDay, hh, m); };

  const clear = (e: React.MouseEvent) => {
    e.stopPropagation();
    onChange("");
    setSelDay(0);
  };

  const handleDone = () => {
    setOpen(false);
  };

  return (
    <div className="bl-dtp">
      {label && <label className="bl-dtp-label">{label}</label>}

      {/* trigger */}
      <button
        type="button"
        className={`bl-dtp-trigger ${value ? "has-value" : ""}`}
        onClick={() => setOpen(true)}
      >
        <CalendarIcon size={15} className="bl-dtp-icon" />
        <span className="bl-dtp-display">
          {value ? formatDisplay(value) : placeholder}
        </span>
        {value && (
          <span className="bl-dtp-clear" onClick={clear}>
            <X size={13} />
          </span>
        )}
      </button>

      {/* popup overlay — portaled to body so parent transforms don't break position:fixed */}
      {open && createPortal(
        <div className="bl-dtp-overlay" onClick={() => setOpen(false)}>
          <div className="bl-dtp-popup" onClick={(e) => e.stopPropagation()}>
            {/* header */}
            <div className="bl-dtp-popup-header">
              <h4>
                <CalendarIcon size={16} />
                Pick Date & Time
              </h4>
              <button type="button" className="bl-dtp-popup-close" onClick={() => setOpen(false)}>
                <X size={18} />
              </button>
            </div>

            {/* calendar body */}
            <div className="bl-dtp-popup-body">
              {/* month nav */}
              <div className="bl-dtp-nav">
                <button type="button" onClick={prevMonth}><ChevronLeft size={16} /></button>
                <span className="bl-dtp-nav-title">{MONTHS[viewMonth]} {viewYear}</span>
                <button type="button" onClick={nextMonth}><ChevronRight size={16} /></button>
              </div>

              {/* day-of-week header */}
              <div className="bl-dtp-dow">
                {DAYS.map((d) => <span key={d}>{d}</span>)}
              </div>

              {/* days grid */}
              <div className="bl-dtp-grid">
                {cells.map((d, i) =>
                  d === null ? (
                    <span key={`e${i}`} className="bl-dtp-cell empty" />
                  ) : (
                    <button
                      key={d}
                      type="button"
                      className={`bl-dtp-cell ${isToday(d) ? "today" : ""} ${isSelected(d) ? "selected" : ""} ${isDayPast(d) ? "disabled" : ""}`}
                      onClick={() => !isDayPast(d) && pickDay(d)}
                      disabled={isDayPast(d)}
                    >
                      {d}
                    </button>
                  )
                )}
              </div>

              {/* time picker */}
              <div className="bl-dtp-time">
                <Clock size={14} className="bl-dtp-time-icon" />
                <Dropdown
                  value={String(hh)}
                  onChange={changeHour}
                  options={hourOpts}
                  placeholder="Hour"
                  className="bl-dtp-time-dropdown"
                />
                <span className="bl-dtp-colon">:</span>
                <Dropdown
                  value={String(mm)}
                  onChange={changeMin}
                  options={minOpts}
                  placeholder="Min"
                  className="bl-dtp-time-dropdown"
                />
              </div>
            </div>

            {/* footer */}
            <div className="bl-dtp-popup-footer">
              <button type="button" className="bl-dtp-btn-clear" onClick={clear}>
                Clear
              </button>
              <button type="button" className="bl-dtp-btn-done" onClick={handleDone}>
                Done
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default DateTimePicker;
