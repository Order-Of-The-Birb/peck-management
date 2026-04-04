from datetime import datetime, timedelta, UTC
from dateutil.parser import isoparse
from typing import Literal

sqb_brackets = (
	(1, 7),
	(14, 22)
)

def toUnix(time:datetime|str):
	return int((isoparse(time) if isinstance(time, str) else time).timestamp())
def discord_timestamp(timestamp:int|datetime, type:Literal["t", "T", "d", "D", "f", "F", "R"]) -> str:
	""" <t:UNIX TIMESTAMP:type>  
	## Types
	- Short Time: t
	- Long Time: T
	- Short Date: d
	- Long Date: D
	- Long Date with Short Time: f
	- Long Date with Day of week and Short Time: F
	- Relative: R
	"""
	if isinstance(timestamp, datetime):
		return f"<t:{toUnix(timestamp)}:{type}>"
	return f"<t:{timestamp}:{type}>"
def get_sqb_timebracket(include_current:bool=True) -> tuple[datetime, datetime]:
	rn = datetime.now(UTC)
	global sqb_brackets
	today_brackets = [
		(rn.replace(hour=start, minute=0, second=0, microsecond=0),
		 rn.replace(hour=end, minute=0, second=0, microsecond=0))
		for start, end in sqb_brackets
	]
	if include_current:
		for start, end in today_brackets: # Currently IN the bracket
			if start <= rn < end:
				return start, end
	future_brackets = [(start, end) for start, end in today_brackets if start > rn] # Find next bracket
	if future_brackets:
		return min(future_brackets, key=lambda r: r[0])
	return today_brackets[0][0]+timedelta(days=1), today_brackets[0][1]+timedelta(days=1)
def isInTimebracket(bracket:tuple[datetime]|None=None) -> bool:
	if bracket is None:
		bracket = get_sqb_timebracket()
	return bracket[0] <= datetime.now(UTC) < bracket[1]