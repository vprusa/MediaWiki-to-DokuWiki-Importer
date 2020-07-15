from common.base_login import base_login
from conf.properties import properties
from selenium import webdriver
from selenium.webdriver.firefox.firefox_binary import FirefoxBinary
from selenium.common.exceptions import StaleElementReferenceException
from common.ui_utils import ui_utils

import os

import logging
import logging.config
from selenium.webdriver.firefox.options import Options

class session(properties):

    web_driver = None

    logger = None
    logging_level = logging.DEBUG
    ui_utils = None

    def __init__(self):
        # call parent method to load properties from files
        super(session, self).__init__()

        self.__logger__()

        self.logger.info("Test properties")
        self.logger.info("Debug: " + self.DEBUG)
        self.logger.info("PROPERTIES_FILE_NAME: " + self.PROPERTIES_FILE_NAME)


        ''' Get the Selenium Web Driver, and then navegate to ...'''
        self.__get_web_driver__()

        self.ui_utils = ui_utils(self)

    def __get_web_driver__(self):
        #self.logger.info(self.BROWSER)
        self.logger.info("Using Browser: %s", self.BROWSER)
        if "Firefox" in self.BROWSER:
            profile = webdriver.FirefoxProfile()
            # enable auto download
            # http://stackoverflow.com/questions/24852709/how-do-i-automatically-download-files-from-a-pop-up-dialog-using-selenium-python
            profile.set_preference("browser.download.folderList", 2)
            profile.set_preference("browser.download.manager.showWhenStarting", False)
            profile.set_preference("browser.download.dir", self.FIREFOX_DOWNLOAD_DIR)
            profile.set_preference("browser.download.panel.shown", False)
            #profile.set_preference("browser.helperApps.neverAsk.openFile","text/csv,application/vnd.ms-excel,text/plain, application/xls,application/x-xls,application/excel,application/x-excel,application/vnd.ms-excel,application/x-excel,application/x-msexcel")
            #profile.set_preference("browser.helperApps.neverAsk.saveToDisk", "text/csv,application/vnd.ms-excel,text/plain, application/xls,application/x-xls,application/excel,application/x-excel,application/vnd.ms-excel,application/x-excel,application/x-msexcel")
            profile.accept_untrusted_certs = True
            options = Options()
            if "True" in self.HEADLESS:
                options.add_argument("--headless")

            binary = FirefoxBinary('./firefox/firefox')
            #driver = webdriver.Firefox(firefox_binary=binary)
            from selenium.webdriver.common.desired_capabilities import DesiredCapabilities
            profile.set_preference("webdriver_accept_untrusted_certs", True)
            desired_caps = DesiredCapabilities.FIREFOX.copy()
            desired_caps.update({'acceptInsecureCerts': True, 'acceptSslCerts': True})

            driver = webdriver.Firefox(capabilities=desired_caps, firefox_options=options, firefox_profile=profile, executable_path="./geckodriver", firefox_binary=binary)

            #self.web_driver = getattr(webdriver,self.BROWSER)(firefox_profile=profile)
            self.web_driver = driver
        if "Chrome" in self.BROWSER:
            driver = webdriver.Chrome(self.DRIVER_LOCATION)  # Optional argument, if not specified will search path.

            self.web_driver = driver


        #self.web_driver.set_window_size(self.BROWSER_WIDTH, self.BROWSER_HEIGHT)

        return

    def __logger__(self):

        self.logger = logging.getLogger('session')

        if len(self.logger.handlers[:]) == 0:
            self.logger.setLevel(logging.DEBUG)

            # create formatter
            formatter = logging.Formatter('%(asctime)s - %(levelname)s: %(message)s')
            ch = logging.StreamHandler()
            ch.setLevel(self.logging_level)
            ch.setFormatter(formatter)
            self.logger.addHandler(ch)

            self.logger.info("Logger Handler created.")
        else:
            self.logger.info("Logger Handler already created.")

    def propertiesByPrefix(self, prefix):
        return [x for x in dir(self) if prefix in x ]

    def close_web_driver(self):
        if not "True" in self.KEEP_BROWSER_RUNNING:
            # close browser window
            # self.web_driver.close()
            # close browser windows & exit webdriver
            self.web_driver.quit()

    def find_elements_by_xpath(self, xpath, retry = 10):
        try:
            return self.web_driver.find_elements_by_xpath(xpath)
        except StaleElementReferenceException as e:
            if retry <= 0:
                raise StaleElementReferenceException()
            return self.find_elements_by_xpath(xpath, retry-1)
        return None

    def find_element_by_xpath(self, xpath, retry = 10):
        elems = self.find_elements_by_xpath(xpath, retry)
        if not elems or len(elems) < 1:
            return None
        return elems[0]

    def getattr(self, item, attr):
        try:
            ret = getattr(item, attr)
            return ret
        except AttributeError:
            return ""
        pass
