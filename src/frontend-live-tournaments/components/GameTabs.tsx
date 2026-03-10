/**
 * Game Rule Tabs — filter tournaments by game
 */

import React from 'react';
import type { GameRule } from '../types';
import { Gamepad2 } from 'lucide-react';

interface GameTabsProps {
  rules: GameRule[];
  activeSlug: string;        // '' = All
  onSelect: (slug: string) => void;
  loading?: boolean;
}

const GameTabs: React.FC<GameTabsProps> = ({ rules, activeSlug, onSelect, loading }) => {
  if (loading) {
    return (
      <div className="bl-lt-tabs">
        <div className="bl-lt-tabs-skeleton">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="bl-lt-tab-skeleton" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="bl-lt-tabs">
      <div className="bl-lt-tabs-scroll">
        <button
          className={`bl-lt-tab ${activeSlug === '' ? 'active' : ''}`}
          onClick={() => onSelect('')}
        >
          <Gamepad2 size={16} />
          <span>All Games</span>
        </button>

        {rules.map((rule) => (
          <button
            key={rule.slug}
            className={`bl-lt-tab ${activeSlug === rule.slug ? 'active' : ''}`}
            onClick={() => onSelect(rule.slug)}
          >
            {rule.game_icon ? (
              <img src={rule.game_icon} alt="" className="bl-lt-tab-icon" />
            ) : (
              <Gamepad2 size={16} />
            )}
            <span>{rule.game_name}</span>
          </button>
        ))}
      </div>
    </div>
  );
};

export default GameTabs;
