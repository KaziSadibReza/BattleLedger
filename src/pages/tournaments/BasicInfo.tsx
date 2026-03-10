import React from "react";
import {
  Type,
  AlignLeft,
  CalendarCheck2,
  AlertCircle,
} from "lucide-react";
import DateTimePicker from "../../components/DateTimePicker";

interface FormData {
  name: string;
  description: string;
  end_date: string;
}

interface Props {
  data: FormData;
  highlightErrors?: boolean;
  autoOpenDate?: boolean;
  onChange: (patch: Partial<FormData>) => void;
}

/** Step 2 — name, description, end date */
const BasicInfo: React.FC<Props> = ({ data, highlightErrors, autoOpenDate, onChange }) => {
  const endInPast = !!data.end_date && new Date(data.end_date).getTime() <= Date.now();
  const nameEmpty = !data.name.trim();
  const dateEmpty = !data.end_date;

  return (
    <div className="bl-t-step-fields">
      {/* Name */}
      <div className={`bl-t-field${highlightErrors && nameEmpty ? " bl-t-field--error" : ""}`}>
        <label><Type size={14} /> Tournament Name *</label>
        <input
          type="text"
          placeholder="e.g. Weekly Clash Squad #12"
          value={data.name}
          onChange={(e) => onChange({ name: e.target.value })}
        />
      </div>

      {/* Description */}
      <div className="bl-t-field">
        <label><AlignLeft size={14} /> Description</label>
        <textarea
          rows={3}
          placeholder="Brief description or rules summary..."
          value={data.description}
          onChange={(e) => onChange({ description: e.target.value })}
        />
      </div>

      {/* End Date */}
      <div className={`bl-t-field${highlightErrors && (dateEmpty || endInPast) ? " bl-t-field--error" : ""}`}>
        <label><CalendarCheck2 size={14} /> End Date *</label>
        <DateTimePicker
          value={data.end_date}
          onChange={(v) => onChange({ end_date: v })}
          placeholder="Select end date & time…"
          disablePast
          autoOpen={autoOpenDate}
        />
        {endInPast && (
          <span className="bl-t-field-error">
            <AlertCircle size={12} />
            Please set a future end date
          </span>
        )}
      </div>
    </div>
  );
};

export default BasicInfo;
