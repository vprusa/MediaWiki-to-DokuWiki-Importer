import time
from selenium.common.exceptions import NoSuchElementException
from random import sample
from common.files_utils import files_utils
from common.timeout import timeout
from selenium.webdriver.common.by import By
from selenium.webdriver.common.action_chains import ActionChains
from pprint import pprint
from selenium.common.exceptions import StaleElementReferenceException

class ui_utils():

    web_session = None
    web_driver = None

    WAIT_VERY_SHORT=30
    WAIT_SHORT=30
    WAIT_MEDIUM=60
    WAIT_LONG=300
    WAIT_EXTRA_LONG=600
    WAIT_EXTRA_SUPER_LONG=3600

    def __init__(self, web_session):
        self.web_session = web_session
        self.web_driver = web_session.web_driver
        self.WAIT_VERY_SHORT = int(web_session.WAIT_VERY_SHORT)
        self.WAIT_SHORT = int(web_session.WAIT_SHORT)
        self.WAIT_MEDIUM = int(web_session.WAIT_MEDIUM)
        self.WAIT_LONG = int(web_session.WAIT_LONG)
        self.WAIT_EXTRA_LONG = int(web_session.WAIT_EXTRA_LONG)
        self.WAIT_EXTRA_SUPER_LONG = int(web_session.WAIT_EXTRA_SUPER_LONG)

    def isTextOnPage(self, text):
        # Just visible text - http://stackoverflow.com/a/651801
        if self.web_driver.find_elements_by_xpath(".//*[contains(text(), '" + text + "') and not (ancestor::*[contains( @ style,'display:none')]) and not (ancestor::*[contains( @ style, 'display: none')])]"):
            return True
        else:
            return False

    # if exist=False then we wait till texts disappears (waitTillTextOnPage[Not]Exists)
    def waitForTextOnPage(self, text, waitTime, exist=True, refresh=False):
        waitTime=int(waitTime)
        try:
            text=text.encode("iso-8859-2").decode("UTF-8")
        except UnicodeDecodeError as ude:
            text=text

        if exist:
            self.web_session.logger.info("Waiting " + str(waitTime) + " seconds for text: " + text)
        else:
            self.web_session.logger.info("Waiting " + str(waitTime) + " seconds text to dissapear: " + text)
        currentTime = time.time()
        ## print "Waiting for text: " + text
        isTextOnPage = self.isTextOnPage(text)
        while (( not isTextOnPage and exist ) or
                   ( isTextOnPage and not exist )) :
            if time.time() - currentTime >= waitTime:
                self.web_session.logger.info("Timed out waiting for: %s", text)
                if "Strict" in self.web_session.DEBUG:
                    files_utils.createScreenshot(self.web_session,"waitForTextOnPage-" + files_utils.simpleStr(text))
                return False
            else:
                if not exist and refresh:
                    self.web_driver.refresh()
                #time.sleep(1)
            time.sleep(1)
            self.web_session.logger.info("Waiting for '" + text + "' in UI")
            isTextOnPage = self.isTextOnPage(text)
        return True

    def isElementPresent(self, locatormethod, locatorvalue):
        try:
            self.web_driver.find_element(by=locatormethod, value=locatorvalue)
        except NoSuchElementException:
            return False
        return True

    def closePossibleModalDialog(self):
        for modalDialogText in self.web_session.propertiesByPrefix( "TX_MODAL_QUESTION"):
            if self.isTextOnPage(modalDialogText):
                self.web_session.logger.info("Closing modal dialog with text: " + modalDialogText)
                actions = ActionChains(browser)
                actions.send_keys(Keys.ESCAPE)
                actions.perform()
                self.waitForTextOnPage(modalDialogText, self.WAIT_VERY_SHORT, exist=False)

    # if exist=False then refresh the page and wait till element disappears
    def waitForElementOnPage(self, locatormethod, locatorvalue, waitTime, exist=True, refresh=False, show_as_error=True):
        currentTime = time.time()
        waitTime=int(waitTime)
        #self.closePossibleModalDialog()
        isElementPresent = self.isElementPresent(locatormethod, locatorvalue)
        #self.closePossibleModalDialog()
        while ((not isElementPresent and exist) or
                   (isElementPresent and not exist)):
            #self.closePossibleModalDialog()
            if time.time() - currentTime >= waitTime:
                if show_as_error:
                    self.web_session.logger.error("Timed out waiting for: %s", locatorvalue)
                else:
                    self.web_session.logger.info("Timed out waiting for: %s", locatorvalue)
                if "Strict" in self.web_session.DEBUG:
                    files_utils.createScreenshot(self.web_session,"waitForElementOnPage-" + files_utils.simpleStr(locatorvalue))
                return False
            else:
                if not exist and refresh:
                    self.web_driver.refresh()
                time.sleep(1)
                isElementPresent = self.isElementPresent(locatormethod, locatorvalue)

        return True

    def waitForProgressBarToFinish(self, xpath, waitTime = WAIT_EXTRA_SUPER_LONG, maxNoProgressWait = WAIT_EXTRA_LONG):
        waitTime=int(waitTime)
        maxNoProgressWait=int(maxNoProgressWait)
        self.web_session.logger.info("Waiting for progress bar with xpath %s to finish with waitTime: %d and maxNoProgressWait: %d", xpath, waitTime, maxNoProgressWait)
        def getProgressBarIntValue(text):
            return int(text.replace("%","").replace(" ",""))

        locatormethod = By.XPATH
        self.waitForElementOnPage(locatormethod, xpath, maxNoProgressWait)

        lastTime = time.time()
        currentProgressBar = None
        currentProgressBarValue = 0
        lastProgressBarValue = 0
        isElementPresent = True
        #self.isElementPresent(By.XPATH,xpath)
        while isElementPresent:
            self.closePossibleModalDialog()
            if time.time() - lastTime >= waitTime:
                self.web_session.logger.info("Timed out waiting for progress bar with xpath: %s with time limit %s" , locatorvalue, waitTime)
                return False
            else:
                self.closePossibleModalDialog()
                currentProgressBar = self.web_driver.find_elements_by_xpath(xpath)
                if len(currentProgressBar) == 0:
                    isElementPresent = self.isElementPresent(locatormethod, xpath)
                    continue
                try:
                    currentProgressBarValue = getProgressBarIntValue(currentProgressBar[0].text)
                except StaleElementReferenceException:
                    continue

                if currentProgressBarValue > lastProgressBarValue:
                    lastProgressBarValue = currentProgressBarValue
                else:
                    # if time of waiting preceeds maxNoProgressWait return false - TODO is necessary?
                    if time.time() - lastTime >= maxNoProgressWait:
                        self.web_session.logger.info("Waited long enough for progress bar to change value from " + str(lastProgressBarValue))
                        return False
                time.sleep(1)
                if (int(time.time() - lastTime)) % 5 == 0:
                    self.web_session.logger.info("Waiting for progress bar to finish %d out of %d seconds with progress %d percent", time.time() - lastTime, waitTime, currentProgressBarValue)
                isElementPresent = self.isElementPresent(locatormethod, xpath)

        return True

    def wait_until_element_displayed(self, element, waitTime):

        with timeout(waitTime, error_message="Timed out waiting for element to be displayed."):
            while True:
                if element.is_displayed():
                    break;
                time.sleep(1)

        return True

    def sleep(self, waitTime):
        time.sleep(waitTime)

    def adjust_screen_resolution(self, horizontal, vertical):
        self.web_driver.set_window_size(horizontal, vertical)

    # debug method
    def downloadPageViaBrowser(self):
        self.web_session.logger.info("Trying to download page using PyKeyboard")
        try:
            time.sleep(2)
            from pykeyboard import PyKeyboard
            k = PyKeyboard()
            k.press_key(k.control_key)
            k.tap_key('s')
            k.release_key(k.control_key)
            time.sleep(3)
            k.tap_key(k.enter_key)
        except:
            self.web_session.logger.error("Exception cougth - but will be ignored")
            sysExecInfo = sys.exc_info()
            self.web_session.logger.error(sysExecInfo[0])
            traceback.print_exc()

    # returns boolean if page has table
    def hasTable(self):
        if self.web_driver.find_elements_by_xpath(self.web_session.XP_TABLE_BODY):
            return True
        else:
            return False
        pass

    def getAllTableRecordsOnPage(self):
        #
        if self.hasTable():
            return []
        ses = self.web_session
        ses.get

        pass

    def showTablesMaxRecords(self, maxRecords = 100):
        pass

    def navigateNextTablePage(self):
        pass

    def navigatePrevTablePage(self):
        pass
