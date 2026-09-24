const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1280,1080']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 1080 });
    
    // Base URL
    const baseUrl = 'http://127.0.0.1:8000';

    try {
        // 1. Homepage (No City Selected)
        console.log('Capturing Homepage (No City)...');
        await page.goto(`${baseUrl}/`);
        await new Promise(r => setTimeout(r, 2000));
        await page.screenshot({ path: 'browser_homepage_no_city.png', fullPage: true });

        // 2. Select City
        console.log('Capturing Homepage (With City)...');
        await page.goto(`${baseUrl}/?city_id=1`);
        await new Promise(r => setTimeout(r, 2000));
        await page.screenshot({ path: 'browser_homepage_with_city.png', fullPage: true });

        // 3. Login Page
        console.log('Capturing Login Page...');
        await page.goto(`${baseUrl}/login`);
        await new Promise(r => setTimeout(r, 1000));
        await page.screenshot({ path: 'browser_login_page.png' });

        // 4. Admin Edit City (To show Banner and Featured Config)
        console.log('Capturing Admin City Edit Page...');
        await page.goto(`${baseUrl}/local-admin-login?redirect=/admin/cities/1/edit`);
        await new Promise(r => setTimeout(r, 3000));
        await page.screenshot({ path: 'browser_admin_city_edit.png', fullPage: true });

        console.log('All screenshots captured successfully.');
    } catch (e) {
        console.error('Error taking screenshots:', e);
    } finally {
        await browser.close();
    }
})();
