import React, { useState } from "react";
import { Loader2 } from "lucide-react";
import TournamentList from "./tournaments/TournamentList";
import TournamentForm from "./tournaments/TournamentForm";
import { useGameRules } from "../lib/useGameRules";

const Tournaments: React.FC = () => {
  const [games, gamesLoading] = useGameRules();
  /* undefined = no modal, null = create, number = edit */
  const [editId, setEditId] = useState<number | null | undefined>(undefined);
  const [reactivate, setReactivate] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);

  if (gamesLoading) {
    return (
      <div className="bl-t-page-loading">
        <Loader2 size={24} className="bl-spin" />
      </div>
    );
  }

  return (
    <>
      <TournamentList
        key={refreshKey}
        games={games}
        onCreate={() => { setReactivate(false); setEditId(null); }}
        onEdit={(id) => { setReactivate(false); setEditId(id); }}
        onReactivate={(id) => { setReactivate(true); setEditId(id); }}
      />
      {editId !== undefined && (
        <TournamentForm
          tournamentId={editId}
          games={games}
          reactivate={reactivate}
          onClose={() => { setEditId(undefined); setReactivate(false); }}
          onSaved={() => {
            setEditId(undefined);
            setReactivate(false);
            setRefreshKey((k) => k + 1);
          }}
        />
      )}
    </>
  );
};

export default Tournaments;
