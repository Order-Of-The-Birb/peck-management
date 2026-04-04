import logging, discord
from sys import modules as sysmodules
from threading import Lock
from importlib import reload
from discord.ext import commands, tasks
from datetime import datetime, UTC, timedelta
from typing import TYPE_CHECKING
from os import getenv
if __name__ == "__main__":
	from os import path
	from sys import path as sys_path
	sys_path.append(path.abspath(path.join(path.dirname(__file__), '..')))
from modules.db import UserRepository
from utils.wt import Squadron
if TYPE_CHECKING:
	from modules.newsAPI import NewsAPI
	from asyncio import AbstractEventLoop
logger = logging.getLogger(__name__)

class Bot(commands.Bot):
	ltsLock = Lock()
	class gaijinLogin:
		email:str
		password:str
		def __init__(self):
			self.email = getenv("WT_LOGIN_EMAIL")
			self.password = getenv("WT_LOGIN_PASS")
	class _planSQB:
		def __init__(self):
			self.announcementMessage:discord.Message|None = None
			self.announced:bool = False
			self.applicantInChannel:bool = False
			self.applicants:list[int] = []
			self.timeframe:tuple[datetime] = ()
			self.applicantJoinedChannel = False
	class _genericTimeout:
		task:tasks.Loop = None
		def __init__(self, count:int=3):
			self.count:int = count
			self.cooldowns:dict[int, list[datetime]] = {
				# dc_id: [times]
			}
		def timed_out(self, user_id:int) -> bool:
			tmp = self.cooldowns.get(user_id, None)
			if tmp is None:
				return False
			return len(tmp) >= self.count
		def getOldest(self, user_id:int) -> datetime|None:
			tmp = self.cooldowns.get(user_id, None)
			if tmp is None:
				return None
			tmp.sort(key=lambda x: x.timestamp())
			return tmp[0]
		def addCooldown(self, user_id:int):
			if user_id in self.cooldowns:
				self.cooldowns[user_id].append(datetime.now(UTC))
			else:
				self.cooldowns.update({user_id:[datetime.now(UTC)]})
	class _squadVC:
		channels:list['SquadVCData'] = []
		checkDelay:int = 0
		class SquadVCData:
			_id:int
			last_seen_person_time:float
			creator:int|None
			created:float
			def __init__(self, _id:int, last_seen_person_time:float, creator:int|None, created:float):
				self._id = _id
				self.last_seen_person_time = last_seen_person_time
				self.creator = creator
				self.created = created
	def __init__(self, *args,**kwargs):
		self.debug:bool = kwargs.pop("debug")
		if not self.debug:
			self.logsChID = 1229136570498682900
			self.spamChID = 1229136570498682900
			self.announcementsChID = 931263111992848395
			self.peckServer = 917850361019125820
			self.wtNewsChID = 920311640161943552
			self.sqbChID = 1353122797504827422
			self.memberRoleID = 919803983286128661
			self.pingRoleID = 1065769588849115248
			self.commonRoles = [917852847482232852, 923683349611053106]
			self.clipsCategory = 1428798448102277263
			self.oldTimerRoleID = 917946204736860190
			self.sqbPlanCheckLimit = timedelta(minutes=30)
			self.squadVCCategID = 917850361019125821
			self.scoreChID = 1125129523499896974
			self.propagandaChID = 1066119609914232842
			self.retiredRoleID = 983021494739292250
			# region News
			self.majorNews = 1476690321051222119
			self.eventNews = 1489360745039921222
			# endregion
		else:
			self.logsChID = 1453040931703095550
			self.spamChID = 1453040954343821513
			self.announcementsChID = 1453040983934767277
			self.peckServer = 685948316541779974
			self.wtNewsChID = 1453041016969101458
			self.sqbChID = 1453041059746676837
			self.memberRoleID = 1073297853587398696
			self.pingRoleID = 1456660597616803956
			self.commonRoles = [1124647932696743948]
			self.clipsCategory = 1454228135280119982
			self.oldTimerRoleID = 1454228913742938112
			self.sqbPlanCheckLimit = timedelta(minutes=2)
			self.squadVCCategID = 1453040903626293338
			self.scoreChID = 1456770254972784780
			self.propagandaChID = 1456788537755045931
			self.retiredRoleID = 1456971952165093467
			# region News
			self.majorNews = 1476690625612222647
			self.eventNews = 1489360453233541170
			# endregion
		self.botIDs = [
			1005502691713237035, # Main Bot
			1007702877264941127 # Testing Bot
		]
		self.yoshinoID = 332030423913725953
		self.sqb_season_length = 2
		self.db = UserRepository()
		self.runningSince:datetime = datetime.now(UTC)
		self.iconURL ='https://cdn.discordapp.com/icons/917850361019125820/325129a273dac84e31f9aa5de51f1936.png?size=64'
		self.logLevel:int = kwargs.pop("logLevel")
		self.planSQB = self._planSQB()
		self.clipTimeout = self._genericTimeout()
		self.aiTimeout = self._genericTimeout()
		self.squadVC = self._squadVC()
		self.runtime:AbstractEventLoop = kwargs.pop("runtime")
		self.newsAPI:'NewsAPI' = kwargs.pop("newsAPI", None)
		self.SQUADRON_TAG = "PECK"
		self.squadron = Squadron("Order Of The Birb")
		super().__init__(tree_cls=Tree, *args, **kwargs)
	def _reload_prefix(self, prefix: str):
		to_reload = [
			(name, module)
			for name, module in sysmodules.items()
			if module and name.startswith(prefix)
		]
		for name, module in to_reload:
			try:
				reload(module)
				logger.debug(f"Reloaded module: {name}")
			except Exception:
				logger.exception(f"Failed to reload module: {name}")
	async def reload_extension_with_deps(self,extension: str,*module_prefixes: str):
		"""Reload helper modules, then reload the extension."""
		if isinstance(module_prefixes, str):
			raise TypeError(f"__reload_deps__ must be a tuple, not str")
		if "modules.db" in module_prefixes:
			self.reload_db()
		for prefix in module_prefixes:
			if prefix != "modules.db":
				self._reload_prefix(prefix)
		await self.reload_extension(extension)
	def reload_db(self):
		for name in list(sysmodules):
			if name == "modules.db" or name.startswith("modules.db."):
				reload(sysmodules[name])
		self.db = UserRepository()
class Tree(discord.app_commands.CommandTree):
	async def on_error(self, interaction: discord.Interaction, error: Exception):
		if isinstance(error, discord.app_commands.CheckFailure):
			return
		if not interaction.response.is_done():
			await interaction.response.send_message(
				"Oops! Something went wrong.",
				ephemeral=True
			)
		raise error  # still log real bugs
def debug_only():
	async def predicate(interaction: discord.Interaction) -> bool:
		return interaction.client.debug
	return discord.app_commands.check(predicate)
