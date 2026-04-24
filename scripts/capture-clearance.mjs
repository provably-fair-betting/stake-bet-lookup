#!/usr/bin/env node

/**
 * Capture Cloudflare clearance credentials and sync to the app.
 *
 * Launches a real Chrome browser, waits for the Cloudflare challenge to complete,
 * then POSTs the captured credentials directly to the app via the admin API.
 *
 * Usage:
 *   node capture-clearance.mjs
 *   npm run capture
 *
 * Requires sync-config.json in the same directory. On first run the file is
 * created automatically — edit it with your endpoint and token, then re-run.
 */

import { connect } from 'puppeteer-real-browser';
import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const STAKE_URL = 'https://stake.games';
const CLEARANCE_COOKIE_NAME = 'cf_clearance';
const SYNC_CONFIG_PATH = path.join(__dirname, 'sync-config.json');

const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
  red: '\x1b[31m',
};

function log(message, color = colors.reset) {
  console.log(`${color}${message}${colors.reset}`);
}

async function loadSyncConfig() {
  try {
    return JSON.parse(await fs.readFile(SYNC_CONFIG_PATH, 'utf-8'));
  } catch {
    const template = {
      api: {
        endpoint: 'http://localhost:8080/api/admin/update-clearance',
        token: 'your-raw-admin-token-here',
      },
    };
    await fs.writeFile(SYNC_CONFIG_PATH, JSON.stringify(template, null, 2));
    log('\n⚠️  sync-config.json created — edit it with your token, then re-run\n', colors.yellow);
    process.exit(1);
  }
}

async function syncCredentials(credentials, config) {
  const { endpoint, token } = config.api;

  if (!endpoint || token === 'your-raw-admin-token-here') {
    log('\n⚠️  Configure sync-config.json with your token, then re-run\n', colors.yellow);
    return;
  }

  log('\n🌐 Syncing credentials...', colors.cyan);

  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(credentials),
  });

  if (!response.ok) {
    throw new Error(`Sync failed: ${response.status} — ${await response.text()}`);
  }

  const result = await response.json();
  log('✅ Synced — ' + JSON.stringify(result), colors.green);
}

async function captureClearance() {
  log('\n🚀 Capturing Cloudflare Clearance', colors.bright);
  log('═'.repeat(60), colors.blue);

  const config = await loadSyncConfig();

  let browser;

  try {
    log('\n📂 Launching Chrome...', colors.cyan);

    const { browser: realBrowser, page } = await connect({
      headless: false,
      args: ['--start-maximized'],
      turnstile: true,
      disableXvfb: false,
      customConfig: {},
    });

    browser = realBrowser;

    log('✅ Browser launched', colors.green);
    log(`\n🌐 Navigating to ${STAKE_URL}...`, colors.cyan);
    await page.goto(STAKE_URL, { waitUntil: 'networkidle2' });

    log('\n⏳ Complete the Cloudflare challenge in the browser window...', colors.yellow);

    let clearanceCookie = null;
    let attempts = 0;

    while (!clearanceCookie && attempts < 120) {
      const cookies = await page.cookies();
      clearanceCookie = cookies.find(c => c.name === CLEARANCE_COOKIE_NAME);

      if (!clearanceCookie) {
        await new Promise(resolve => setTimeout(resolve, 1000));
        if (++attempts % 10 === 0) {
          log(`   Still waiting... (${attempts}s elapsed)`, colors.yellow);
        }
      }
    }

    if (!clearanceCookie) {
      throw new Error('Timed out waiting for clearance cookie.');
    }

    log('\n✅ Clearance cookie captured!', colors.green);

    const userAgent = await page.evaluate(() => navigator.userAgent);

    log('\n' + '═'.repeat(60), colors.blue);
    log('📋 CAPTURED', colors.bright);
    log('═'.repeat(60), colors.blue);
    log(`\n🍪 Cookie  : ${clearanceCookie.value.substring(0, 40)}...`, colors.reset);
    log(`🌐 Agent   : ${userAgent.substring(0, 60)}...`, colors.reset);
    log(`⏱  Expires : ${new Date(clearanceCookie.expires * 1000).toISOString()}`, colors.reset);

    await syncCredentials({
      clearance: clearanceCookie.value,
      userAgent,
      expiry: Math.floor(clearanceCookie.expires),
    }, config);

    log('\n' + '═'.repeat(60), colors.blue);
    log('✨ Done!\n', colors.green);

    log('Closing browser in 3 seconds...', colors.yellow);
    await new Promise(resolve => setTimeout(resolve, 3000));

  } catch (error) {
    log('\n❌ ' + error.message, colors.red);
    process.exit(1);
  } finally {
    if (browser) await browser.close();
  }
}

captureClearance().catch(error => {
  log('\n💥 ' + error.stack, colors.red);
  process.exit(1);
});
