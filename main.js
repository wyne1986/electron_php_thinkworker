const {app, globalShortcut, BrowserWindow, ipcMain } = require('electron')
const path = require('path')
const url = require('url')
const fs = require('fs')
const process = require('child_process');

/*
electron with php server
please take the php7+ directory to the path ./app/php7/
take the thinkworker program directory to the path ./app/thinkworker/
*/

// Keep a global reference of the window object, if you don't, the window will
// be closed automatically when the JavaScript object is garbage collected.
let win

function createWindow () {
  // Create the browser window.
  win = new BrowserWindow({
	  title:'php thinkworker dev tools (F8 fullscreen, F9 restart thinkworker, F12 DevTools)',
	  frame: true,
	  width: 800,
	  height: 600,
	  resizable: false
  });
  
  win.setFullScreen(false);
    
  //press F8 to toggle fullscreen off/on
  globalShortcut.register('F8', () => {
        win.isFullScreen?win.setFullScreen(false):win.setFullScreen(true);
  });  
  
  //press F9 to restart php server
  globalShortcut.register('F9', () => {
	restartServer();
  });
  
  //press F12 to toggle Browser DevTools
  globalShortcut.register('F12', () => {
	win.webContents.isDevToolsOpened() ? win.webContents.closeDevTools() : win.webContents.openDevTools();
  });

  //press ESC to close app
  globalShortcut.register('ESC', () => {
        win.close();
  });
  
  //load local server page, you can change the port but must be same with the php server port
  win.loadURL('http://127.0.0.1:8888/')

  // Open the DevTools.
  if(debug)win.webContents.openDevTools();

  // Emitted when the window is closed.
  win.on('closed', () => {
    // Dereference the window object, usually you would store windows
    // in an array if your app supports multi windows, this is the time
    // when you should delete the corresponding element.
	stopServer()
    win = null
  })
  if(debug>0){
	  debuger();
  }
}

// This method will be called when Electron has finished
// initialization and is ready to create browser windows.
// Some APIs can only be used after this event occurs.
app.on('ready', createWindow)

// Quit when all windows are closed.
app.on('window-all-closed', () => {
  // On macOS it is common for applications and their menu bar
  // to stay active until the user quits explicitly with Cmd + Q
  if (process.platform !== 'darwin') {
    app.quit()
  }
})

app.on('activate', () => {
  // On macOS it's common to re-create a window in the app when the
  // dock icon is clicked and there are no other windows open.
  if (win === null) {
    createWindow()
  }
})

/*
if debug on, when you file changed , the server will restart and show your changed file;
and if debug>1,there will be a debug window, when php get error, it will be alert you
*/
let debug = 1; //false, no debug, 1 devtools and file wathcer and page refresh, 2 debuger window
let awin

//php server function
let bat;
function stopServer(){
	if(bat){
		bat.kill('SIGINT',bat.pid);
		bat = null;
	}
}
function startServer(){
	bat = process.spawn(path.join(__dirname, 'php7','php'),[path.join(__dirname, 'thinkworker','start.php'),'start'],{
		'stdio':['ignore'],
		'detached':true
	});
	if(debug>1){
		bat.stdout.on('data', (data) => {
		  awin.webContents.send('alertwin-message',data.toString());
		});
		bat.stderr.on('data', (data) => {
		  awin.webContents.send('alertwin-error',data.toString());
		});
	}
}
function restartServer(){
	stopServer();
	startServer();
}
function debuger(){
	  if(debug>1){
		awin = new BrowserWindow({
		  frame: true,
		  width: 600,
		  height: 300,
		  resizable: true,
		  parent: win
		});
		//awin.webContents.openDevTools();
		awin.loadURL(path.join(__dirname, 'alert.html'));
		
		awin.on('closed', () => {
		  awin = null
		})
	  }
		
	  setTimeout(function(){
		startServer();
		win.reload();
	  },1000);
		  
	//thinkworker file watcher, when file changed, restart php server
	let lastUpdateTime = 0;
	function watchwebs(dir) {
	  fs.watch(dir, (event, filename)=> {
		var diff = Date.now() - lastUpdateTime
		lastUpdateTime = Date.now()
		if (diff < 100) return
		  restartServer();
		  win.reload()
	  })

	  //foreach directory
	  var files = fs.readdirSync(dir);
	  for (var i = 0; i < files.length; i++) {
		var file = dir + '/' + files[i]
		var stat = fs.statSync(file)
		if (stat.isDirectory() == true) {
		  watchwebs(file);
		}
	  }
	}
	watchwebs(path.join(__dirname, 'thinkworker'))
}
if(!debug){
	startServer();
}
// In this file you can include the rest of your app's specific main process
// code. You can also put them in separate files and require them here.