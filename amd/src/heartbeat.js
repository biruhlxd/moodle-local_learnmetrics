// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * "Still here" pings: while a learner has a page of a course open and is using it, tell Moodle every minute.
 *
 * A ping is sent only when the tab is visible and the learner has used the page, or a frame of it from the same
 * site, in the last 5 minutes. On the SCORM player, a package served from another site cannot be watched: there,
 * a visible tab is enough.
 *
 * @module     local_learnmetrics/heartbeat
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

/** @var {number} Time between two pings, in milliseconds. */
const INTERVAL = 60 * 1000;

/** @var {number} Longest time without interaction that still counts, in milliseconds. */
const IDLE = 5 * 60 * 1000;

/** @var {number} The same in the SCORM player, where a video can play for several minutes without a save. */
const SCORM_IDLE = 15 * 60 * 1000;

/** @var {string[]} Events that show the learner is using the page. Pointer events too: some packages use only those. */
const EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'wheel', 'touchstart', 'pointerdown', 'pointermove'];

/**
 * @var {Object} The SCORM interfaces Moodle's player provides to packages, and the calls that save a value.
 * A package saves its progress through them as the learner moves through it (Storyline saves at each slide),
 * even when the learner only reads or watches, and the calls reach the player page wherever the package is served from.
 */
const SCORM_APIS = {API: 'LMSSetValue', API_1484_11: 'SetValue'};

/** @var {RegExp} Values packages may save on a timer, which do not show that the learner is there. */
const TIME_ELEMENTS = /(session_time|total_time)$/;

let lastInteraction = Date.now();
let crossOriginFrame = false;
let timer = null;

/**
 * Remember that the learner used the page.
 */
const markActive = () => {
    lastInteraction = Date.now();
};

/**
 * Watch a window and its frames for interaction. Adding the same listener twice has no effect.
 *
 * @param {Window} win
 */
const watchWindow = (win) => {
    try {
        EVENTS.forEach((name) => win.document.addEventListener(name, markActive, {passive: true, capture: true}));
    } catch (e) {
        // A frame from another site cannot be watched.
        crossOriginFrame = true;
        return;
    }
    for (let i = 0; i < win.frames.length; i++) {
        watchWindow(win.frames[i]);
    }
};

/**
 * Count the SCORM package saving a value as the learner being active. Each interface is watched once.
 */
const watchScormApi = () => {
    Object.entries(SCORM_APIS).forEach(([name, method]) => {
        const api = window[name];
        if (!api || typeof api[method] !== 'function' || api.localLearnmetricsWatched) {
            return;
        }
        const save = api[method];
        api[method] = function(element, ...rest) {
            if (!TIME_ELEMENTS.test(String(element))) {
                markActive();
            }
            return save.call(this, element, ...rest);
        };
        api.localLearnmetricsWatched = true;
    });
};

/**
 * Send a ping when the learner is there.
 *
 * @param {number} courseId
 * @param {boolean} scormPlayer
 */
const tick = (courseId, scormPlayer) => {
    // Frames can be added or reloaded at any time, as a SCORM package moves from page to page.
    watchWindow(window);
    if (scormPlayer) {
        watchScormApi();
    }
    if (document.visibilityState !== 'visible') {
        return;
    }
    const visibilityIsEnough = scormPlayer && crossOriginFrame;
    if (!visibilityIsEnough && Date.now() - lastInteraction > (scormPlayer ? SCORM_IDLE : IDLE)) {
        return;
    }
    fetchMany([{methodname: 'local_learnmetrics_heartbeat', args: {courseid: courseId}}])[0].catch(() => {
        // The session may have ended: stop quietly.
        window.clearInterval(timer);
    });
};

/**
 * Start sending pings.
 *
 * @param {number} courseId The course of the page.
 * @param {boolean} scormPlayer Whether the page is the SCORM player.
 */
export const init = (courseId, scormPlayer) => {
    if (timer) {
        return;
    }
    watchWindow(window);
    if (scormPlayer) {
        watchScormApi();
    }
    timer = window.setInterval(() => tick(courseId, scormPlayer), INTERVAL);
};
