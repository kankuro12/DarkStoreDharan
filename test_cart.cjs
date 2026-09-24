const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: "new",
        args: ['--no-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 1080 });
        
        console.log('1. Going to homepage');
        await page.goto('http://127.0.0.1:8000/?city_id=1');
        await new Promise(r => setTimeout(r, 2000));

        // Let's find an add button and click it
        console.log('2. Clicking ADD button');
        const addBtn = await page.$('button[onclick^="addToCart"]');
        if (addBtn) {
            await addBtn.click();
            console.log('Clicked add button. Waiting for JS to run...');
            await new Promise(r => setTimeout(r, 3000)); // wait for fetch + fetch HTML + swap
        } else {
            console.log('No add button found!');
        }

        console.log('3. Opening cart drawer');
        await page.evaluate(() => {
            const drawer = document.getElementById('cart-drawer');
            if (drawer) drawer.showModal();
        });
        await new Promise(r => setTimeout(r, 1000));

        await page.screenshot({ path: 'browser_cart_drawer_fixed.png' });
        console.log('Screenshot saved.');

    } catch (e) {
        console.error(e);
    } finally {
        await browser.close();
    }
})();
