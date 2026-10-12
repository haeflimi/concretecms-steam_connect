<?php

namespace SteamConnect\Figure;

use CommunityEventManager\Figure\CallbackFigure;
use CommunityEventManager\Figure\FigureContext;
use CommunityEventManager\Figure\FigureOptions as O;
use CommunityEventManager\Figure\FigureRegistry;
use CommunityEventManager\Figure\FigureValue;

/**
 * Figures for the block "Event Figure" (community_event_manager), registered on on_event_figures_register.
 */
class SteamFigures
{
    public static function register(FigureRegistry $registry): void
    {
        $group = t('Steam');
        $registry->register(CallbackFigure::make('steam.accounts', t('Connected Steam accounts'), $group, 'fab fa-steam', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM SteamConnectProfiles WHERE uID IS NOT NULL'));
        }));

        $registry->register(CallbackFigure::make('steam.hours', t('Hours played (optionally one game)'), $group, 'fas fa-gamepad', [O::TEXT], static function (FigureContext $c, array $o) {
            $game = trim((string) ($o[O::TEXT] ?? ''));
            $minutes = $game === ''
                ? $c->db->fetchOne('SELECT SUM(playtimeForever) FROM SteamConnectOwnedGames')
                : $c->db->fetchOne('SELECT SUM(g.playtimeForever) FROM SteamConnectOwnedGames g JOIN SteamConnectApps a ON a.appId = g.appId WHERE a.name LIKE ?', ['%' . $game . '%']);

            return FigureValue::number((int) round(((int) $minutes) / 60));
        }));

        $registry->register(CallbackFigure::make('steam.games', t('Different games owned'), $group, 'fas fa-layer-group', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(DISTINCT appId) FROM SteamConnectOwnedGames'));
        }));

        $registry->register(CallbackFigure::make('steam.achievements', t('Achievements unlocked'), $group, 'fas fa-trophy', [], static function (FigureContext $c, array $o) {
            return FigureValue::number($c->db->fetchOne('SELECT COUNT(*) FROM SteamConnectPlayerAchievements'));
        }));

        $registry->register(CallbackFigure::make('steam.top_game', t('Most played game'), $group, 'fas fa-fire', [], static function (FigureContext $c, array $o) {
            return FigureValue::text($c->db->fetchOne('SELECT a.name FROM SteamConnectOwnedGames g JOIN SteamConnectApps a ON a.appId = g.appId GROUP BY g.appId, a.name ORDER BY SUM(g.playtimeForever) DESC LIMIT 1') ?: null);
        }));

        $registry->register(CallbackFigure::make('steam.most_owned', t('Game owned by the most members'), $group, 'fas fa-users', [], static function (FigureContext $c, array $o) {
            return FigureValue::text($c->db->fetchOne('SELECT a.name FROM SteamConnectOwnedGames g JOIN SteamConnectApps a ON a.appId = g.appId GROUP BY g.appId, a.name ORDER BY COUNT(*) DESC LIMIT 1') ?: null);
        }));
    }
}
