# Generated from Selenium IDE
# Test name: t09 Assign pack minimization
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_09_Assign_pack_minimization:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_09_Assign_pack_minimization(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Pack Management Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Pack Management Test").click()
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.execute_script("$('input[name=\"cat_id\"]').val('packs9')")
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    time.sleep(2)
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.NAME, "enabled").find_element(By.CSS_SELECTOR, "*[value='1']").click()
    self.driver.find_element(By.NAME, "trigger").find_element(By.CSS_SELECTOR, "*[value='M']").click()
    self.driver.find_element(By.NAME, "nominim").find_element(By.CSS_SELECTOR, "*[value='S']").click()
    self.driver.find_element(By.NAME, "dags").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "blocks").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "expire").find_element(By.CSS_SELECTOR, "*[value='0']").click()
    self.driver.find_element(By.NAME, "packfield").find_element(By.CSS_SELECTOR, "*[value='pack_id']").click()
    self.driver.find_element(By.NAME, "countfield").find_element(By.CSS_SELECTOR, "*[value='pack_count']").click()
    self.driver.find_element(By.NAME, "roles_view").send_keys("PackView")
    self.driver.find_element(By.NAME, "roles_invalid").send_keys("PackInvalid")
    self.driver.find_element(By.NAME, "roles_assign").send_keys("PackAssign")
    self.driver.find_element(By.NAME, "roles_add").send_keys("PackAdd")
    self.driver.find_element(By.NAME, "roles_edit").send_keys("PackEdit")
    self.driver.find_element(By.CSS_SELECTOR, "#catform button.btn-primaryrc").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs9\"]")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=packs\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=packs_add\"][href*=\"cat_id=packs9\"]").click()
    self.driver.find_element(By.CSS_SELECTOR, "a[href=\"#multiplepacks\"]").click()
    self.driver.execute_script("fd=new FormData($('form[enctype=\"multipart/form-data\"]')[0]);fd.set('packs_upload',new Blob([decodeURIComponent('id,value%0A1,A%0A2,B%0A3,B')]));fetch( window.location.href, {body:fd, method:'post'})")
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]").click()
    self.driver.execute_script("$.get(window.location.href+'&runtest=assignMinimPack&record_id=1&list_minim_codes=%5B%22A%22%2C%22B%22%5D&minim_field=',function(data){$('body').attr('data-result',data)})")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-result]")))
    self.driver.execute_script("res=JSON.parse($('body').attr('data-result'));if(res.packID == '1' && res.randoCode == 'A'){$('body').attr('data-minim','1')}")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-minim]")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"prefix=pack_management\"][href*=\"page=configure\"]:not([href*=\"logout=1\"])").click()
    self.driver.execute_script("$.get(window.location.href+'&runtest=assignMinimPack&record_id=1&list_minim_codes=%5B%22A%22%2C%22B%22%5D&minim_field=',function(data){$('body').attr('data-result',data)})")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-result]")))
    self.driver.execute_script("res=JSON.parse($('body').attr('data-result'));if(res.randoCode == 'B'){$('body').attr('data-minim','1')}")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-minim]")) > 0
    self.driver.find_element(By.CSS_SELECTOR, "a[href*=\"page=configure_edit\"][href*=\"cat_id=packs9\"]").click()
    self.driver.execute_script("$('#south').remove()")
    self.driver.find_element(By.NAME, "nominim").find_element(By.CSS_SELECTOR, "*[value='P']").click()
    self.driver.find_element(By.CSS_SELECTOR, "input[type=\"submit\"]").click()
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.ID, "south")))
    self.driver.execute_script("$.get(window.location.href+'&runtest=assignMinimPack&record_id=1&list_minim_codes=%5B%22A%22%2C%22B%22%5D&minim_field=',function(data){$('body').attr('data-result',data)})")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-result]")))
    self.driver.execute_script("res=JSON.parse($('body').attr('data-result'));if(res === false){$('body').attr('data-minim','1')}")
    assert len(self.driver.find_elements(By.CSS_SELECTOR, "body[data-minim]")) > 0
